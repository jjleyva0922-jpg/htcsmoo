<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Requisition;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisitionRoleRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_requisitions_start_after_the_requestors_own_approval_office(): void
    {
        $departmentHead = User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $vpFinance = User::factory()->create(['role' => 'vp_finance']);
        $vpAdmin = User::factory()->create(['role' => 'vp_admin']);
        $provost = User::factory()->create(['role' => 'provost']);
        $president = User::factory()->create(['role' => 'president']);
        $smo = User::factory()->create(['role' => 'smo']);
        $employee = User::factory()->create(['role' => 'employee', 'department' => 'CETE']);
        $minorSupply = $this->supply('minor', 100);
        $majorSupply = $this->supply('major', 1500);

        $this->submit($employee, $minorSupply, 'minor');
        $this->assertRoute($employee, 'pending', $departmentHead, 'requisition.awaiting_approval');

        $this->submit($departmentHead, $majorSupply, 'major');
        $this->assertRoute($departmentHead, 'approved_dept', $vpAdmin, 'requisition.awaiting_approval');

        $this->submit($vpAdmin, $majorSupply, 'major');
        $this->assertRoute($vpAdmin, 'approved_vp', $provost, 'requisition.awaiting_approval');

        $this->submit($vpAdmin, $minorSupply, 'minor');
        $this->assertRoute($vpAdmin, 'approved_vp', $smo, 'requisition.ready_for_release');

        $this->submit($vpFinance, $minorSupply, 'minor');
        $this->assertRoute($vpFinance, 'approved_vp', $smo, 'requisition.ready_for_release');

        $this->submit($vpFinance, $majorSupply, 'major');
        $this->assertRoute($vpFinance, 'approved_vp', $provost, 'requisition.awaiting_approval');

        $this->submit($president, $majorSupply, 'major');
        $this->assertRoute($president, 'approved_president', $smo, 'requisition.ready_for_release');

        $this->submit($provost, $minorSupply, 'minor');
        $this->assertRoute($provost, 'approved_president', $smo, 'requisition.ready_for_release');
    }

    public function test_president_and_provost_requests_appear_first_in_the_smo_release_queue(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $president = User::factory()->create(['name' => 'Priority President', 'role' => 'president']);
        $provost = User::factory()->create(['name' => 'Priority Provost', 'role' => 'provost']);
        $employee = User::factory()->create(['name' => 'Regular Requestor', 'role' => 'employee']);

        $presidentRequest = $this->readyRequest($president, '2026-10-01 08:00:00');
        $provostRequest = $this->readyRequest($provost, '2026-10-02 08:00:00');
        $regularRequest = $this->readyRequest($employee, '2026-10-03 08:00:00');

        $this->actingAs($smo)
            ->get(route('admin.approvals'))
            ->assertOk()
            ->assertSeeInOrder([
                $presidentRequest->tracking_code,
                $provostRequest->tracking_code,
                $regularRequest->tracking_code,
            ]);
    }

    private function submit(User $requestor, Supply $supply, string $tier): void
    {
        $this->actingAs($requestor)->post(route('requisitions.store'), [
            'request_type' => $tier,
            'purpose' => 'Institutional supplies required for official operations.',
            'items' => [['supply_id' => $supply->id, 'qty' => 1]],
        ])->assertRedirect(route('dashboard'));
    }

    private function assertRoute(User $requestor, string $status, User $recipient, string $event): void
    {
        $requisition = Requisition::where('user_id', $requestor->id)->latest('id')->firstOrFail();

        $this->assertSame($status, $requisition->status);
        $this->assertTrue(Notification::where([
            'user_id' => $recipient->id,
            'event_type' => $event,
            'action_id' => $requisition->id,
        ])->exists());
    }

    private function supply(string $tier, float $price): Supply
    {
        return Supply::create([
            'item_name' => ucfirst($tier).' Routing Test Supply',
            'category' => 'Office Supplies',
            'requisition_tier' => $tier,
            'quantity' => 20,
            'unit' => 'Unit(s)',
            'unit_price' => $price,
            'min_stock_level' => 1,
        ]);
    }

    private function readyRequest(User $requestor, string $createdAt): Requisition
    {
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'major',
            'status' => 'approved_president',
            'purpose' => 'Queue priority verification request.',
            'grand_total' => 1500,
        ]);
        $requisition->timestamps = false;
        $requisition->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $requisition->fresh();
    }
}
