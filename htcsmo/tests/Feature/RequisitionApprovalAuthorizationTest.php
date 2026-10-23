<?php

namespace Tests\Feature;

use App\Models\ApprovalLog;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RequisitionApprovalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signatory_and_smo_actions_are_wired_to_the_shared_progress_dialog(): void
    {
        foreach (['dept_head', 'vp_finance', 'vp_admin', 'provost', 'president', 'smo'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.approvals'))->assertOk()
                ->assertSee('id="runtimeActionModal"', false)
                ->assertSee('data-action-progress', false)
                ->assertSee('runtime-action-modal::backdrop', false);
        }

        $this->actingAs(User::factory()->create(['role' => 'smo']))->get(route('admin.approvals'))
            ->assertOk()->assertSee('Starting SMO processing...')
            ->assertSee('Marking supplies ready for pickup...')
            ->assertSee('Confirming pickup and completion...');
        $this->actingAs(User::factory()->create(['role' => 'dept_head']))->get(route('admin.approvals'))
            ->assertOk()->assertSee('Recording your approval...')
            ->assertSee('Recording your rejection...')->assertSee('Requesting office clarification...');
    }

    public function test_department_head_cannot_approve_when_it_is_vp_finances_turn(): void
    {
        $requestor = User::factory()->create(['department' => 'CETE']);
        $departmentHead = User::factory()->create([
            'role' => 'dept_head',
            'department' => 'CETE',
        ]);
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'approved_dept',
            'grand_total' => 100,
        ]);

        $this
            ->actingAs($departmentHead)
            ->post(route('admin.status.update', $requisition), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'status' => 'approved_dept',
        ]);
    }

    public function test_department_head_cannot_resolve_a_vp_finance_clarification(): void
    {
        $requestor = User::factory()->create(['department' => 'CETE']);
        $departmentHead = User::factory()->create([
            'role' => 'dept_head',
            'department' => 'CETE',
        ]);
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'for_clarification',
            'grand_total' => 100,
        ]);
        ApprovalLog::create([
            'requisition_id' => $requisition->id,
            'action' => 'Flagged for Office Clarification by VP for Finance',
            'role' => 'vp_finance',
        ]);

        $this
            ->actingAs($departmentHead)
            ->post(route('admin.status.update', $requisition), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'status' => 'for_clarification',
        ]);
    }

    public function test_smo_cannot_release_a_requisition_before_its_required_approvals_are_complete(): void
    {
        $requestor = User::factory()->create();
        $smo = User::factory()->create(['role' => 'smo']);
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'grand_total' => 100,
        ]);

        $this
            ->actingAs($smo)
            ->from(route('admin.approvals'))
            ->post(route('admin.requests.processing', $requisition))
            ->assertRedirect(route('admin.approvals'))
            ->assertSessionHas('error', 'This requisition has not completed its required approval route.');

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('approval_logs', [
            'requisition_id' => $requisition->id,
            'action' => 'Processing Started by SMO In-Charge',
        ]);
    }

    public function test_failed_release_does_not_partially_deduct_duplicate_catalog_lines(): void
    {
        $requestor = User::factory()->create();
        $smo = User::factory()->create(['role' => 'smo']);
        $supply = Supply::create([
            'item_name' => 'Bond Paper',
            'brand' => 'Test Brand',
            'category' => 'Office Supplies',
            'requisition_tier' => 'minor',
            'specifications' => 'A4',
            'quantity' => 5,
            'unit' => 'Ream',
            'unit_price' => 100,
            'min_stock_level' => 1,
        ]);
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'processing',
            'grand_total' => 600,
        ]);

        foreach (range(1, 2) as $line) {
            RequisitionItem::create([
                'requisition_id' => $requisition->id,
                'supply_id' => $supply->id,
                'item_name' => "Bond Paper line {$line}",
                'quantity' => 3,
                'unit' => 'Ream',
                'unit_price' => 100,
                'subtotal' => 300,
            ]);
        }

        $this
            ->actingAs($smo)
            ->from(route('admin.approvals'))
            ->post(route('admin.requests.ready-for-pickup', $requisition))
            ->assertRedirect(route('admin.approvals'))
            ->assertSessionHas('error', "Insufficient stock for 'Bond Paper'. Available: 5, Requested: 6.");

        $this->assertSame(5, $supply->fresh()->quantity);
        $this->assertSame('processing', $requisition->fresh()->status);
        $this->assertDatabaseMissing('supply_inventory_logs', ['requisition_id' => $requisition->id]);
        $this->assertDatabaseMissing('approval_logs', [
            'requisition_id' => $requisition->id,
            'action' => 'Marked Ready for Pickup by SMO In-Charge',
        ]);
    }

    public function test_approved_requisition_release_commits_stock_audit_and_notification_together(): void
    {
        Mail::fake();
        $requestor = User::factory()->create();
        $smo = User::factory()->create(['role' => 'smo']);
        $supply = Supply::create([
            'item_name' => 'Printer Ink',
            'brand' => 'Test Brand',
            'category' => 'Office Supplies',
            'requisition_tier' => 'minor',
            'specifications' => 'Black',
            'quantity' => 10,
            'unit' => 'Bottle',
            'unit_price' => 150,
            'min_stock_level' => 2,
        ]);
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'approved_vp',
            'grand_total' => 300,
        ]);
        RequisitionItem::create([
            'requisition_id' => $requisition->id,
            'supply_id' => $supply->id,
            'item_name' => $supply->item_name,
            'quantity' => 2,
            'unit' => 'Bottle',
            'unit_price' => 150,
            'subtotal' => 300,
        ]);

        $this
            ->actingAs($smo)
            ->post(route('admin.requests.processing', $requisition))
            ->assertRedirect(route('admin.approvals'))
            ->assertSessionHas('success');

        $this
            ->actingAs($smo)
            ->post(route('admin.requests.ready-for-pickup', $requisition))
            ->assertRedirect(route('admin.approvals'))
            ->assertSessionHas('success');

        $this->assertSame('ready_for_pickup', $requisition->fresh()->status);
        $this->assertSame(8, $supply->fresh()->quantity);
        $this->assertDatabaseHas('supply_inventory_logs', [
            'requisition_id' => $requisition->id,
            'supply_id' => $supply->id,
            'quantity_change' => -2,
            'balance_after' => 8,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $requestor->id,
            'event_type' => 'requisition.ready_for_pickup',
            'action_id' => $requisition->id,
        ]);
        $this->assertDatabaseHas('approval_logs', [
            'requisition_id' => $requisition->id,
            'acted_by_user_id' => $smo->id,
            'action' => 'Marked Ready for Pickup by SMO In-Charge',
        ]);

        $this->actingAs($smo)
            ->post(route('admin.requests.complete', $requisition))
            ->assertSessionHas('success');

        $this->assertSame('completed', $requisition->fresh()->status);
    }
}
