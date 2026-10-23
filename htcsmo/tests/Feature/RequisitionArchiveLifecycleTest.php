<?php

namespace Tests\Feature;

use App\Models\ApprovalLog;
use App\Models\DigitalSignature;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequisitionArchiveLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_withdrawal_preserves_items_prior_history_and_signatures_and_records_actor_and_closure(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 30));
        $owner = User::factory()->create();
        $record = $this->requisition($owner, 'pending');
        $item = RequisitionItem::create(['requisition_id' => $record->id, 'item_name' => 'Retained paper',
            'quantity' => 2, 'unit' => 'Ream', 'unit_price' => 100, 'subtotal' => 200]);
        $previous = $this->log($record, 'Submitted by requestor', '2026-10-21 10:00:00');
        $signature = DigitalSignature::create(['approval_log_id' => $previous->id, 'signature_token' => str_repeat('a', 64),
            'signer_name' => $owner->name, 'signer_role' => 'employee', 'ip_address' => '127.0.0.1',
            'user_agent' => 'Archive regression test', 'signed_at' => now()->subDay()]);

        $this->actingAs($owner)->delete(route('requisitions.destroy', $record), ['withdrawal_reason' => '  Duplicate order <script>alert(1)</script>  '])
            ->assertRedirect(route('requests.archive', ['category' => 'withdrawn']))->assertSessionHas('success');
        $record->refresh();
        $this->assertSame('withdrawn', $record->status);
        $this->assertTrue($record->closed_at->equalTo(now()));
        $this->assertDatabaseHas('requisition_items', ['id' => $item->id, 'subtotal' => 200]);
        $this->assertDatabaseHas('approval_logs', ['id' => $previous->id, 'action' => 'Submitted by requestor']);
        $this->assertDatabaseHas('digital_signatures', ['id' => $signature->id, 'signature_token' => str_repeat('a', 64)]);
        $event = $record->logs()->where('action', 'Withdrawn by requestor')->sole();
        $this->assertSame($owner->id, $event->acted_by_user_id);
        $this->assertSame('employee', $event->role);
        $this->assertTrue($event->created_at->equalTo($record->closed_at));
        $this->assertSame('Duplicate order <script>alert(1)</script>', $event->remarks);
        $this->get(route('requests.show', $record))->assertOk()->assertSee('Withdrawn')
            ->assertSee($event->remarks)->assertDontSee('<script>alert(1)</script>', false)->assertSee($owner->name)
            ->assertDontSee('Live workflow status');
        $this->get(route('requests.index'))->assertOk()->assertDontSee($record->tracking_code);
        $this->from(route('requests.index'))->delete(route('requisitions.destroy', $record))->assertSessionHas('error');
        $this->assertSame(2, $record->logs()->count());
        $this->assertTrue($record->fresh()->closed_at->equalTo($event->created_at));
        $this->assertDatabaseCount('supply_inventory_logs', 0);
    }

    public function test_only_the_owner_can_withdraw_and_only_while_pending(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $pending = $this->requisition($owner, 'pending');
        $this->actingAs($other)->delete(route('requisitions.destroy', $pending))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'smo']))->delete(route('requisitions.destroy', $pending))->assertNotFound();
        $this->assertSame('pending', $pending->fresh()->status);
        $this->actingAs($owner);
        foreach (['approved_dept', 'approved_vp', 'approved_provost', 'approved_president', 'processing', 'ready_for_pickup',
            'for_clarification', 'completed', 'released', 'rejected', 'withdrawn'] as $status) {
            $record = $this->requisition($owner, $status);
            $closure = $record->closed_at;
            $this->from(route('requests.index'))->delete(route('requisitions.destroy', $record))
                ->assertRedirect(route('requests.index'))->assertSessionHas('error');
            $this->assertSame($status, $record->fresh()->status);
            $this->assertEquals($closure, $record->closed_at);
            $this->assertSame(0, $record->logs()->count());
        }
        $this->from(route('requests.index'))->delete(route('requisitions.destroy', $pending), ['withdrawal_reason' => str_repeat('x', 501)])
            ->assertSessionHasErrors('withdrawal_reason');
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame(0, $pending->logs()->count());
        $this->delete(route('requisitions.destroy', $pending))->assertSessionHas('success');
        $this->assertSame('Withdrawn by the requestor before approval.', $pending->logs()->sole()->remarks);
    }

    public function test_completion_backfill_uses_actual_handover_audit_events_and_rollback_preserves_other_dates(): void
    {
        $owner = User::factory()->create();
        $completed = $this->requisition($owner, 'completed');
        $laterChanged = $this->requisition($owner, 'completed');
        $unknown = $this->requisition($owner, 'completed');
        $onlyPrepared = $this->requisition($owner, 'completed');
        $active = $this->requisition($owner, 'ready_for_pickup');
        $existing = $this->requisition($owner, 'completed');
        $existingDate = $existing->closed_at;
        foreach ([$completed, $laterChanged, $unknown, $onlyPrepared] as $record) {
            DB::table('requisitions')->where('id', $record->id)->update(['closed_at' => null]);
        }
        $this->log($completed, 'Completed by SMO In-Charge', '2026-09-01 10:00:00');
        $actual = $this->log($completed, 'Completed by SMO In-Charge', '2026-09-02 23:59:59');
        $this->log($completed, 'Marked Ready for Pickup by SMO In-Charge', '2026-09-04 10:00:00');
        $this->log($onlyPrepared, 'Marked Ready for Pickup by SMO In-Charge', '2026-09-02 10:00:00');
        $this->log($laterChanged, 'Completed by SMO In-Charge', '2026-09-02 12:00:00');
        $this->log($active, 'Completed by SMO In-Charge', '2026-09-02 12:00:00');
        $this->log($existing, 'Completed by SMO In-Charge', '2026-09-02 12:00:00');

        $migration = require database_path('migrations/2026_10_22_000001_backfill_completed_requisition_closures.php');
        $migration->up();
        $migration->up();
        $this->assertSame('2026-09-02 23:59:59', $completed->fresh()->closed_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('requisition_closure_backfills', ['requisition_id' => $completed->id, 'approval_log_id' => $actual->id]);
        $this->assertDatabaseCount('requisition_closure_backfills', 2);
        $this->assertNull($unknown->fresh()->closed_at);
        $this->assertNull($onlyPrepared->fresh()->closed_at);
        $this->assertNull($active->fresh()->closed_at);
        $this->assertTrue($existing->fresh()->closed_at->equalTo($existingDate));
        $this->actingAs($owner)->get(route('requests.archive', ['date_from' => '2026-09-02', 'date_to' => '2026-09-02']))
            ->assertOk()->assertSee($completed->tracking_code)->assertSee($laterChanged->tracking_code)
            ->assertDontSee($unknown->tracking_code)->assertDontSee($onlyPrepared->tracking_code);
        $laterChanged->update(['closed_at' => '2026-09-03 12:00:00']);
        $migration->down();
        $this->assertNull($completed->fresh()->closed_at);
        $this->assertSame('2026-09-03 12:00:00', $laterChanged->fresh()->closed_at->format('Y-m-d H:i:s'));
        $this->assertTrue($existing->fresh()->closed_at->equalTo($existingDate));
        $this->assertDatabaseHas('approval_logs', ['id' => $actual->id, 'action' => 'Completed by SMO In-Charge']);
    }

    public function test_deleting_an_owner_cannot_cascade_away_requisitions_and_audit_records(): void
    {
        $owner = User::factory()->create();
        $record = $this->requisition($owner, 'withdrawn');
        $log = $this->log($record, 'Withdrawn by requestor', '2026-10-22 10:00:00');
        try {
            $owner->delete();
            $this->fail('Deleting a requisition owner must be rejected by the foreign key.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $exception->getMessage());
        }
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('requisitions', ['id' => $record->id]);
        $this->assertDatabaseHas('approval_logs', ['id' => $log->id]);
        // Accounts with no requisition ownership retain their existing deletion behavior.
        $unused = User::factory()->create();
        $unused->delete();
        $this->assertDatabaseMissing('users', ['id' => $unused->id]);
    }

    private function requisition(User $owner, string $status): Requisition
    {
        return Requisition::create(['user_id' => $owner->id, 'request_type' => 'minor', 'status' => $status, 'grand_total' => 200]);
    }

    private function log(Requisition $record, string $action, string $createdAt): ApprovalLog
    {
        $log = new ApprovalLog(['requisition_id' => $record->id, 'acted_by_user_id' => $record->user_id, 'action' => $action, 'role' => 'smo']);
        $log->created_at = $createdAt;
        $log->save();

        return $log;
    }
}
