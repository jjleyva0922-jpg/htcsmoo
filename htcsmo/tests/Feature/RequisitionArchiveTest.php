<?php

namespace Tests\Feature;

use App\Models\ApprovalLog;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequisitionArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_contains_released_and_rejected_requests_without_exposing_other_users_records(): void
    {
        $employee = User::factory()->create();
        $otherEmployee = User::factory()->create();
        $released = $this->requisition($employee, 'released', '2026-09-02 09:00:00');
        $rejected = $this->requisition($employee, 'rejected', '2026-09-03 09:00:00');
        $active = $this->requisition($employee, 'pending');
        $private = $this->requisition($otherEmployee, 'released', '2026-09-04 09:00:00');

        $this->actingAs($employee)
            ->get(route('requests.archive'))
            ->assertOk()
            ->assertSee($released->tracking_code)
            ->assertSee($rejected->tracking_code)
            ->assertDontSee($active->tracking_code)
            ->assertDontSee($private->tracking_code);
    }

    public function test_status_department_and_closure_date_filters_work_together_and_ignore_updated_at(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $cete = User::factory()->create(['department' => 'CETE']);
        $cas = User::factory()->create(['department' => 'CAS']);
        $match = $this->requisition($cete, 'released', '2026-09-10 08:00:00');
        $wrongStatus = $this->requisition($cete, 'rejected', '2026-09-10 08:00:00');
        $wrongDepartment = $this->requisition($cas, 'released', '2026-09-10 08:00:00');
        $wrongClosureDate = $this->requisition($cete, 'released', '2026-08-10 08:00:00');

        Requisition::withoutTimestamps(fn () => $match->update(['remarks' => 'Edited after closure']));
        $match->forceFill(['updated_at' => '2026-10-01 08:00:00'])->saveQuietly();

        $filters = [
            'status' => 'released',
            'dept' => 'CETE',
            'date_from' => '2026-09-10',
            'date_to' => '2026-09-10',
        ];

        $this->actingAs($smo)
            ->get(route('requests.archive', $filters))
            ->assertOk()
            ->assertSee($match->tracking_code)
            ->assertDontSee($wrongStatus->tracking_code)
            ->assertDontSee($wrongDepartment->tracking_code)
            ->assertDontSee($wrongClosureDate->tracking_code);

        $this->actingAs($smo)
            ->get(route('admin.archive', $filters))
            ->assertOk()
            ->assertSee($match->tracking_code)
            ->assertDontSee($wrongStatus->tracking_code)
            ->assertDontSee($wrongDepartment->tracking_code)
            ->assertDontSee($wrongClosureDate->tracking_code);
    }

    public function test_both_archive_pages_can_search_archived_request_content(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['name' => 'Archive Search Owner']);
        $match = $this->requisition($requestor, 'completed', '2026-09-10 08:00:00');
        $other = $this->requisition($requestor, 'released', '2026-09-11 08:00:00');
        RequisitionItem::create([
            'requisition_id' => $match->id,
            'item_name' => 'Historical projector',
            'specifications' => 'Archive-only marker',
            'quantity' => 1,
            'unit' => 'Unit',
            'unit_price' => 100,
            'subtotal' => 100,
        ]);

        foreach (['requests.archive', 'admin.archive'] as $route) {
            $this->actingAs($smo)
                ->get(route($route, ['search' => 'Archive-only marker']))
                ->assertOk()
                ->assertSee($match->tracking_code)
                ->assertDontSee($other->tracking_code)
                ->assertSee('value="Archive-only marker"', false);
        }
    }

    public function test_archive_sorts_by_closure_date_and_preserves_filters_across_pagination(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['department' => 'CTE']);
        $older = $this->requisition($requestor, 'released', '2026-09-01 08:00:00');
        $newer = $this->requisition($requestor, 'released', '2026-09-30 08:00:00');

        foreach (range(1, 15) as $day) {
            $this->requisition($requestor, 'released', sprintf('2026-09-%02d 12:00:00', min($day + 1, 29)));
        }

        $filters = [
            'status' => 'released',
            'dept' => 'CTE',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'order' => 'asc',
        ];

        $this->actingAs($smo)
            ->get(route('requests.archive', $filters))
            ->assertOk()
            ->assertSee($older->tracking_code)
            ->assertDontSee($newer->tracking_code)
            ->assertSee('status=released', false)
            ->assertSee('dept=CTE', false)
            ->assertSee('date_from=2026-09-01', false)
            ->assertSee('date_to=2026-09-30', false)
            ->assertSee('order=asc', false)
            ->assertSee('page=2', false);

        $this->actingAs($smo)
            ->get(route('requests.archive', [...$filters, 'page' => 2]))
            ->assertOk()
            ->assertSee($newer->tracking_code);

        $this->actingAs($smo)
            ->get(route('requests.archive', [...$filters, 'print' => 1]))
            ->assertOk()
            ->assertSee($older->tracking_code)
            ->assertSee($newer->tracking_code);
    }

    public function test_invalid_archive_date_range_returns_a_clear_validation_error(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->from(route('requests.archive'))
            ->get(route('requests.archive', [
                'date_from' => '2026-09-20',
                'date_to' => '2026-09-01',
            ]))
            ->assertRedirect(route('requests.archive'))
            ->assertSessionHasErrors('date_to');
    }

    public function test_request_details_and_pdf_follow_archive_record_permissions(): void
    {
        $owner = User::factory()->create();
        $otherEmployee = User::factory()->create();
        $smo = User::factory()->create(['role' => 'smo']);
        $requisition = $this->requisition($owner, 'released', '2026-09-10 08:00:00');

        $this->get(route('requests.show', $requisition))->assertRedirect(route('login'));
        $this->actingAs($otherEmployee)->get(route('requests.show', $requisition))->assertForbidden();
        $this->actingAs($otherEmployee)->get(route('requests.download', $requisition))->assertForbidden();
        $this->actingAs($owner)->get(route('requests.show', $requisition))->assertOk();
        $this->actingAs($smo)->get(route('requests.show', $requisition))->assertOk();
    }

    public function test_institutional_archive_is_smo_only_and_archive_views_do_not_mutate_audit_records(): void
    {
        $employee = User::factory()->create();
        $smo = User::factory()->create(['role' => 'smo']);
        $requisition = $this->requisition($employee, 'rejected', '2026-09-10 08:00:00');
        RequisitionItem::create([
            'requisition_id' => $requisition->id,
            'item_name' => 'Archived paper',
            'quantity' => 2,
            'unit' => 'ream',
            'unit_price' => 100,
            'subtotal' => 200,
        ]);
        ApprovalLog::create([
            'requisition_id' => $requisition->id,
            'action' => 'Rejected by Department Head',
            'role' => 'dept_head',
            'remarks' => 'Not approved',
        ]);

        $this->actingAs($employee)->get(route('admin.archive'))->assertRedirect(route('dashboard'));
        $this->actingAs($smo)->get(route('admin.archive'))->assertOk()->assertSee($requisition->tracking_code);

        $this->assertDatabaseHas('requisitions', ['id' => $requisition->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('requisition_items', ['requisition_id' => $requisition->id, 'item_name' => 'Archived paper']);
        $this->assertDatabaseHas('approval_logs', ['requisition_id' => $requisition->id, 'action' => 'Rejected by Department Head']);
    }

    public function test_terminal_transition_date_is_not_changed_by_later_edits(): void
    {
        $employee = User::factory()->create();
        $requisition = $this->requisition($employee, 'pending');

        $requisition->update(['status' => 'rejected']);
        $closedAt = $requisition->fresh()->closed_at;
        $requisition->update(['remarks' => 'Audit note added later']);

        $this->assertNotNull($closedAt);
        $this->assertTrue($requisition->fresh()->closed_at->equalTo($closedAt));
    }

    public function test_categories_separate_withdrawn_rejected_and_fulfilled_records_and_summaries(): void
    {
        $owner = User::factory()->create();
        $smo = User::factory()->create(['role' => 'smo']);
        $completed = $this->requisition($owner, 'completed');
        $released = $this->requisition($owner, 'released');
        $rejected = $this->requisition($owner, 'rejected');
        $withdrawn = $this->requisition($owner, 'withdrawn');
        $private = $this->requisition(User::factory()->create(), 'withdrawn');

        foreach ([[$owner, 'requests.archive'], [$smo, 'admin.archive']] as [$viewer, $route]) {
            $response = $this->actingAs($viewer)->get(route($route, ['category' => 'fulfilled']))
                ->assertOk()->assertSee($completed->tracking_code)->assertSee($released->tracking_code)
                ->assertDontSee($rejected->tracking_code)->assertDontSee($withdrawn->tracking_code)
                ->assertDontSee($private->tracking_code)->assertSee('Released (legacy)');
            $this->assertEquals(2, $response->viewData('summary')->total);
            $this->assertEquals(400, $response->viewData('summary')->requested_value);
            $this->assertEquals(0, $response->viewData('summary')->withdrawn);
            $this->get(route($route, ['category' => 'rejected']))->assertOk()
                ->assertSee($rejected->tracking_code)->assertDontSee($withdrawn->tracking_code)->assertDontSee($completed->tracking_code);
            $this->get(route($route, ['category' => 'withdrawn']))->assertOk()
                ->assertSee($withdrawn->tracking_code)->assertDontSee($rejected->tracking_code)->assertDontSee($completed->tracking_code);
        }
        $response = $this->actingAs($owner)->get(route('requests.archive'))->assertOk()->assertDontSee($private->tracking_code);
        $this->assertEquals(4, $response->viewData('summary')->total);
        $this->assertSame(['all' => 4, 'fulfilled' => 2, 'rejected' => 1, 'withdrawn' => 1], $response->viewData('categoryCounts'));
        $this->assertFalse($withdrawn->isSlaActive());
        $this->assertFalse(Requisition::active()->whereKey($withdrawn)->exists());
        $this->assertFalse(Requisition::whereIn('status', Requisition::FULFILLED_STATUSES)->whereKey($withdrawn)->exists());
    }

    public function test_submission_and_closure_date_bases_are_inclusive_and_unknown_dates_are_not_guessed(): void
    {
        $owner = User::factory()->create();
        $match = $this->requisition($owner, 'completed', '2026-10-02 23:59:59');
        $match->update(['submitted_at' => '2026-09-01 00:00:00']);
        $unknown = $this->requisition($owner, 'completed');
        DB::table('requisitions')->where('id', $unknown->id)->update([
            'closed_at' => null, 'submitted_at' => null, 'created_at' => '2026-09-01 23:59:59',
        ]);
        $this->actingAs($owner);
        $range = ['date_from' => '2026-09-01', 'date_to' => '2026-09-01'];
        $this->get(route('requests.archive', [...$range, 'date_basis' => 'submitted']))->assertOk()
            ->assertSee($match->tracking_code)->assertSee($unknown->tracking_code)->assertSee('Closure date unavailable');
        $this->get(route('requests.archive', $range))->assertOk()
            ->assertDontSee($match->tracking_code)->assertDontSee($unknown->tracking_code);
        $this->get(route('requests.archive', ['date_from' => '2026-10-02', 'date_to' => '2026-10-02']))->assertOk()
            ->assertSee($match->tracking_code)->assertDontSee($unknown->tracking_code);
        $this->get(route('requests.archive', ['date_to' => '2026-10-02']))->assertOk()->assertSee($match->tracking_code);
        $sorted = $this->get(route('requests.archive', ['order' => 'desc']))->assertOk();
        $this->assertSame([$match->id, $unknown->id], $sorted->viewData('records')->pluck('id')->all());
        $this->assertEquals(1, $sorted->viewData('summary')->undated);
        $this->assertNull($unknown->fresh()->closed_at);
    }

    public function test_audit_remarks_search_remains_private_and_clear_filters_removes_search_and_all_other_filters(): void
    {
        $owner = User::factory()->create();
        $match = $this->requisition($owner, 'withdrawn', '2026-09-10 09:00:00');
        $private = $this->requisition(User::factory()->create(), 'withdrawn', '2026-09-10 09:00:00');
        foreach ([$match, $private] as $record) {
            ApprovalLog::create(['requisition_id' => $record->id, 'acted_by_user_id' => $record->user_id,
                'action' => 'Withdrawn by requestor', 'role' => 'employee', 'remarks' => 'Duplicate seminar supplies']);
        }
        $this->actingAs($owner)->get(route('requests.archive', [
            'search' => 'Duplicate seminar', 'category' => 'withdrawn', 'status' => 'withdrawn',
            'date_from' => '2026-09-10', 'date_to' => '2026-09-10', 'date_basis' => 'closed', 'order' => 'asc',
        ]))->assertOk()->assertSee($match->tracking_code)->assertDontSee($private->tracking_code)
            ->assertSee('Duplicate seminar supplies')->assertSee('href="'.route('requests.archive').'#archive-results">Clear all filters', false);

        $clear = $this->get(route('requests.archive'))->assertOk();
        $this->assertSame([], $clear->viewData('filters'));
        $this->assertEquals(1, $clear->viewData('summary')->total);
        $this->get(route('requests.archive', ['search' => 'No match']))->assertOk()->assertSee('No requests match these filters');
        $this->actingAs(User::factory()->create())->get(route('requests.archive'))->assertOk()->assertSee('No archived requisitions found');
        $smo = User::factory()->create(['role' => 'smo']);
        $this->actingAs($smo)->get(route('admin.archive', ['search' => 'Duplicate', 'dept' => $owner->department]))
            ->assertOk()->assertSee('href="'.route('admin.archive').'#archive-results">Clear all filters', false);
    }

    public function test_filtered_exports_and_print_include_all_pages_and_enforce_the_same_privacy_rules(): void
    {
        $owner = User::factory()->create(['department' => 'CETE']);
        $smo = User::factory()->create(['role' => 'smo']);
        $records = collect(range(1, 18))->map(fn () => $this->requisition($owner, 'withdrawn', '2026-09-10 09:00:00'));
        $private = $this->requisition(User::factory()->create(['department' => 'CAS']), 'withdrawn', '2026-09-10 09:00:00');
        $wrongStatus = $this->requisition($owner, 'completed', '2026-09-10 09:00:00');
        $wrongDate = $this->requisition($owner, 'withdrawn', '2026-08-10 09:00:00');
        $first = $records->first();
        RequisitionItem::create(['requisition_id' => $first->id, 'item_name' => '=HYPERLINK("https://example.test")',
            'quantity' => 1, 'unit' => 'Unit', 'unit_price' => 200, 'subtotal' => 200]);
        ApprovalLog::create(['requisition_id' => $first->id, 'acted_by_user_id' => $owner->id,
            'action' => 'Withdrawn by requestor', 'role' => 'employee', 'remarks' => 'Seminar was cancelled']);

        foreach ([[$owner, 'requests.archive'], [$smo, 'admin.archive']] as [$viewer, $route]) {
            $filters = ['category' => 'withdrawn', 'date_from' => '2026-09-10', 'date_to' => '2026-09-10', 'order' => 'asc'];
            if ($viewer->role === 'smo') {
                $filters['dept'] = 'CETE';
            }
            $this->actingAs($viewer);
            $page = $this->get(route($route, $filters))->assertOk();
            $this->assertCount(15, $page->viewData('records'));
            $this->assertEquals(18, $page->viewData('summary')->total);
            $this->assertEquals(3600, $page->viewData('summary')->requested_value);
            $print = $this->get(route($route, [...$filters, 'print' => 1, 'page' => 2]))->assertOk()
                ->assertSee('Seminar was cancelled')->assertDontSee($private->tracking_code)
                ->assertDontSee($wrongStatus->tracking_code)->assertDontSee($wrongDate->tracking_code);
            $this->assertCount(18, $print->viewData('records'));
            $download = $this->get(route($route, [...$filters, 'export' => 'csv', 'page' => 2]))->assertOk()->assertDownload();
            $csv = $download->streamedContent();
            foreach ($records as $record) {
                $this->assertStringContainsString($record->tracking_code, $csv);
                $print->assertSee($record->tracking_code);
            }
            $this->assertStringNotContainsString($private->tracking_code, $csv);
            $this->assertStringNotContainsString($wrongStatus->tracking_code, $csv);
            $this->assertStringNotContainsString($wrongDate->tracking_code, $csv);
            $this->assertStringContainsString("'=HYPERLINK", $csv);
            $this->assertStringContainsString('Requested value (PHP; not expenditure)', $csv);
            $this->assertStringContainsString('Seminar was cancelled', $csv);
            $this->assertSame(19, count(array_filter(explode("\n", trim($csv)))));
        }
        foreach (['dept_head', 'vp_finance', 'vp_admin', 'provost', 'president', 'employee'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ([['export' => 'csv'], ['print' => 1]] as $output) {
                $this->get(route('admin.archive', $output))->assertRedirect(route('dashboard'));
                $this->get(route('requests.archive', $output))->assertOk()->assertDontSee($first->tracking_code);
            }
        }
        $this->actingAs($owner)->from(route('requests.archive'))
            ->get(route('requests.archive', ['dept' => 'CAS', 'export' => 'csv']))
            ->assertRedirect(route('requests.archive'))->assertSessionHasErrors('dept');
    }

    public function test_details_preserve_validated_archive_filters_and_never_use_arbitrary_return_urls(): void
    {
        $owner = User::factory()->create();
        $record = $this->requisition($owner, 'withdrawn');
        $filters = ['search' => 'Archive', 'category' => 'withdrawn', 'date_basis' => 'submitted', 'order' => 'asc', 'page' => 2];
        $this->actingAs($owner)->get(route('requests.show', [$record, 'archive' => 'personal', 'archive_filters' => $filters]))
            ->assertOk()->assertSee('Back to archive results')->assertSee(route('requests.archive', $filters));
        foreach ([
            ['archive' => 'https://evil.test'],
            ['archive' => 'institutional', 'archive_filters' => $filters],
            ['archive' => 'personal', 'archive_filters' => ['status' => 'invalid']],
            ['archive' => 'personal', 'archive_filters' => 'https://evil.test'],
        ] as $context) {
            $this->get(route('requests.show', [$record, ...$context]))->assertOk()->assertSee('Back to dashboard')->assertDontSee('Back to archive results');
        }
        $smo = User::factory()->create(['role' => 'smo']);
        $this->actingAs($smo)->get(route('requests.show', [$record, 'archive' => 'institutional', 'archive_filters' => $filters]))
            ->assertOk()->assertSee(route('admin.archive', $filters));
    }

    private function requisition(User $user, string $status, ?string $closedAt = null): Requisition
    {
        return Requisition::create([
            'user_id' => $user->id,
            'request_type' => 'minor',
            'status' => $status,
            'closed_at' => $closedAt,
            'purpose' => 'Archive behavior test',
            'grand_total' => 200,
        ]);
    }
}
