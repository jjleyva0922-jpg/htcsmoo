<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Requisition;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_requisition_form_uses_a_confirmation_modal_before_submission(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-no-loader @submit="handleRequisitionSubmit($event)"', false)
            ->assertSee('id="requisitionConfirmationTitle"', false)
            ->assertSee('Submit this requisition?')
            ->assertSee('Submitting requisition…')
            ->assertSee('requisition-loading-circle', false)
            ->assertSee('class="cart-pane"', false)
            ->assertSee('name="purpose"', false)
            ->assertDontSee('requisitionCheckoutModal', false)
            ->assertSee('confirmRequisitionSubmit()', false);
    }

    public function test_smo_sees_the_modern_command_center_and_active_pipeline(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['name' => 'Faculty Requestor']);
        $request = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'purpose' => 'Classroom supplies',
            'grand_total' => 500,
        ]);
        $deadlineRequest = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'approved_vp',
            'purpose' => 'Urgent printing supplies',
            'grand_total' => 800,
        ]);
        $deadlineRequest->forceFill([
            'submitted_at' => now()->subDays(4),
            'sla_warning_at' => now()->subDays(2),
            'due_at' => now()->subDay(),
            'stage_started_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->saveQuietly();
        Supply::create([
            'item_name' => 'Low Stock Bond Paper',
            'brand' => 'HTC Test',
            'category' => 'Office Supplies',
            'requisition_tier' => 'minor',
            'specifications' => 'A4 paper',
            'quantity' => 2,
            'unit' => 'Ream',
            'unit_price' => 250,
            'min_stock_level' => 10,
        ]);

        $this->actingAs($smo)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('SMO Command Center')
            ->assertSee('Inventory and fulfillment overview')
            ->assertSee('Active requisitions')
            ->assertSee($request->tracking_number)
            ->assertSee('Faculty Requestor')
            ->assertSee('Deadline monitor')
            ->assertSee($deadlineRequest->tracking_number)
            ->assertSee('Inventory warning')
            ->assertSee('Low Stock Bond Paper')
            ->assertSeeInOrder([
                'Overview',
                'Notifications',
                'Requisitions',
                'Inventory & Fulfillment',
                'Fulfillment Queue',
                'Manage Inventory',
                'Insights',
                'Operational Analytics',
                'Report Center',
                'Administration',
                'Access Requests',
                'Manage Personnel',
            ], false);
    }

    public function test_employee_sees_personal_metrics_and_request_tracking(): void
    {
        $employee = User::factory()->create([
            'name' => 'Maria Santos',
            'role' => 'employee',
        ]);
        $request = Requisition::create([
            'user_id' => $employee->id,
            'request_type' => 'major',
            'status' => 'pending',
            'purpose' => 'Office equipment',
            'grand_total' => 25000,
        ]);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome back, Maria.')
            ->assertSee('New requisition')
            ->assertSee('My active requisitions')
            ->assertSee('My procurement activity')
            ->assertSee($request->tracking_number)
            ->assertSeeInOrder(['Overview', 'Notifications', 'Requisitions', 'Active Requests', 'Request Archive']);
    }

    public function test_department_head_dashboard_filters_requests_by_department_id(): void
    {
        $department = Department::where('dept_code', 'CETE')->firstOrFail();
        $departmentHead = User::factory()->create([
            'role' => 'dept_head',
            'department_id' => $department->id,
        ]);
        $requestor = User::factory()->create([
            'name' => 'CETE Requestor',
            'department_id' => $department->id,
        ]);
        $request = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'purpose' => 'Department laboratory supplies',
            'grand_total' => 1500,
        ]);

        $this->actingAs($departmentHead)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Requests requiring your attention')
            ->assertSee($request->tracking_number)
            ->assertSee('CETE Requestor');
    }

    public function test_smo_dashboard_shows_only_the_ten_most_recent_active_requisitions_without_filters(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['name' => 'Dashboard Requestor']);
        $requests = collect(range(1, 11))->map(function (int $position) use ($requestor) {
            $request = Requisition::create([
                'user_id' => $requestor->id,
                'request_type' => 'minor',
                'status' => 'pending',
                'purpose' => "Dashboard request {$position}",
                'grand_total' => 100,
            ]);
            $request->forceFill([
                'created_at' => now()->subMinutes(11 - $position),
                'updated_at' => now()->subMinutes(11 - $position),
            ])->saveQuietly();

            return $request;
        });

        $this->actingAs($smo)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('allStaffRequests', fn ($activeRequests) => $activeRequests->count() === 10
                && $activeRequests->first()->is($requests->last()))
            ->assertDontSee($requests->first()->tracking_number)
            ->assertSee($requests->last()->tracking_number)
            ->assertDontSee('dashboard-filter-bar', false)
            ->assertDontSee('filter-apply', false);
    }
}
