<?php

namespace Tests\Feature;

use App\Models\InstitutionalHoliday;
use App\Models\Requisition;
use App\Models\User;
use App\Services\BusinessDaySlaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessDaySlaTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_working_day_deadline_skips_weekends(): void
    {
        $submittedAt = Carbon::parse('2026-10-16 10:00:00', 'Asia/Manila');

        $milestones = app(BusinessDaySlaService::class)->milestones($submittedAt);

        $this->assertSame('2026-10-20 10:00:00', $milestones['sla_warning_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-21 10:00:00', $milestones['due_at']->format('Y-m-d H:i:s'));
    }

    public function test_three_working_day_deadline_skips_configured_institutional_holidays(): void
    {
        InstitutionalHoliday::create([
            'holiday_date' => '2026-10-19',
            'name' => 'Institutional Holiday',
        ]);
        $submittedAt = Carbon::parse('2026-10-16 10:00:00', 'Asia/Manila');

        $milestones = app(BusinessDaySlaService::class)->milestones($submittedAt);

        $this->assertSame('2026-10-21 10:00:00', $milestones['sla_warning_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-22 10:00:00', $milestones['due_at']->format('Y-m-d H:i:s'));
    }

    public function test_requisition_captures_a_fixed_submission_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Manila'));
        $requestor = User::factory()->create();

        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'grand_total' => 1000,
        ]);

        $this->assertSame('2026-10-16 10:00:00', $requisition->submitted_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 10:00:00', $requisition->sla_warning_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-21 10:00:00', $requisition->due_at->format('Y-m-d H:i:s'));

        $requisition->update(['status' => 'approved_dept']);

        $this->assertSame('2026-10-16 10:00:00', $requisition->fresh()->submitted_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-21 10:00:00', $requisition->fresh()->due_at->format('Y-m-d H:i:s'));
    }
}
