<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveRequestWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_modern_active_workspace_with_only_their_requests(): void
    {
        $employee = User::factory()->create(['name' => 'Active Employee']);
        $otherEmployee = User::factory()->create(['name' => 'Private Employee']);
        $active = $this->requisition($employee, 'pending');
        $archived = $this->requisition($employee, 'released');
        $private = $this->requisition($otherEmployee, 'pending');

        $this->actingAs($employee)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee('All active')
            ->assertSee('In-progress requests')
            ->assertSee('New submissions')
            ->assertSee($active->tracking_number)
            ->assertDontSee($private->tracking_number)
            ->assertSee('Details');

        $this->actingAs($employee)
            ->get(route('requests.archive'))
            ->assertOk()
            ->assertSee($archived->tracking_number)
            ->assertDontSee($active->tracking_number);
    }

    public function test_smo_can_search_the_institutional_active_pipeline(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['name' => 'Searchable Requestor']);
        $match = $this->requisition($requestor, 'approved_dept');
        $other = $this->requisition($requestor, 'pending');

        $this->actingAs($smo)
            ->get(route('requests.index', ['search' => $match->tracking_number]))
            ->assertOk()
            ->assertSee('In-progress requests')
            ->assertSee($match->tracking_number)
            ->assertDontSee($other->tracking_number)
            ->assertSee('Searchable Requestor');
    }

    public function test_active_request_search_covers_item_details_and_requestor_identity(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $matchingRequestor = User::factory()->create([
            'name' => 'Angela Reyes',
            'school_id' => 'HTC-SEARCH-42',
            'department' => 'CETE',
        ]);
        $otherRequestor = User::factory()->create(['name' => 'Unrelated Requestor', 'department' => 'CAS']);
        $match = $this->requisition($matchingRequestor, 'pending');
        $other = $this->requisition($otherRequestor, 'pending');
        RequisitionItem::create([
            'requisition_id' => $match->id,
            'item_name' => 'Laser Printer',
            'specifications' => 'Duplex network model ZX-900',
            'quantity' => 1,
            'unit' => 'Unit',
            'unit_price' => 100,
            'subtotal' => 100,
        ]);

        foreach (['ZX-900', 'HTC-SEARCH-42', 'CETE'] as $search) {
            $this->actingAs($smo)
                ->get(route('requests.index', ['search' => $search]))
                ->assertOk()
                ->assertSee($match->tracking_number)
                ->assertDontSee($other->tracking_number);
        }
    }

    public function test_active_request_pagination_uses_bootstrap_and_preserves_filters(): void
    {
        $employee = User::factory()->create();

        foreach (range(1, 17) as $index) {
            $this->requisition($employee, 'pending');
        }

        $this->actingAs($employee)
            ->get(route('requests.index', ['status' => 'pending', 'order' => 'asc']))
            ->assertOk()
            ->assertSee('class="pagination"', false)
            ->assertSee('status=pending', false)
            ->assertSee('order=asc', false)
            ->assertSee('page=2', false);
    }

    private function requisition(User $user, string $status): Requisition
    {
        return Requisition::create([
            'user_id' => $user->id,
            'request_type' => 'minor',
            'status' => $status,
            'purpose' => 'Workspace test request',
            'grand_total' => 500,
        ]);
    }
}
