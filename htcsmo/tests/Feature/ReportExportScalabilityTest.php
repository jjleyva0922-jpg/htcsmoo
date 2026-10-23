<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\ApprovalLog;
use App\Models\Requisition;
use App\Models\Supply;
use App\Models\SupplyInventoryLog;
use App\Models\User;
use App\Models\UserActivityLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class ReportExportScalabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_csv_is_deferred_chunked_complete_ordered_and_filtered(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create(['department' => 'CETE', 'name' => '=José']);
        $other = User::factory()->create(['department' => 'CTE']);
        $actions = array_map(fn ($i) => 'Activity '.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(1, 1003));
        array_splice($actions, 0, 5, ['=1+1', '+SUM(A1)', '-1', '@formula', "Unicode ñ, quotes \" and\nnew line"]);
        $this->activities($owner, $actions);
        $this->activities($owner, ['Outside date range'], '2026-09-30 23:59:59');
        $this->activities($other, ['Outside department']);
        $query = ['report_type' => 'user_activity', 'period' => 'custom', 'date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'dept' => 'CETE', 'role' => 'employee'];
        $this->actingAs($smo);
        $preview = $this->get(route('admin.reports.builder', [...$query, 'page' => 2]))->assertOk();
        $this->assertSame(1003, $preview->viewData('pagination')->total());
        $this->assertSame(50, $preview->viewData('summaryValue'));
        $this->assertSame('This page: Recorded User Activities', $preview->viewData('summaryLabel'));

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $this->get(route('admin.reports.builder', [...$query, 'page' => 2, 'export' => 'csv']))->assertOk();
            $this->assertCount(0, $this->selectsFrom('user_activity_logs'), 'CSV rows should be fetched inside the stream, not before it.');
            $csv = $this->csv($response);
            $queries = $this->selectsFrom('user_activity_logs');
        } finally {
            DB::disableQueryLog();
        }
        $this->assertCount(1003, $csv);
        $this->assertSame(array_map($this->escapeCell(...), array_reverse($actions)), array_column($csv, 'Activity'));
        $this->assertSame(["'=José"], array_values(array_unique(array_column($csv, 'User'))));
        $this->assertGreaterThan(1, count($queries));
        foreach ($queries as $sql) {
            $this->assertMatchesRegularExpression('/limit 200\b/i', $sql, 'Every result query must be bounded.');
        }
    }

    public function test_large_requisition_and_grouped_csv_include_every_line_and_an_empty_request(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        $owner = User::factory()->create(['department' => 'CETE']);
        $request = $this->requisition($owner);
        // Submission and completion dates intentionally disagree.
        $request->forceFill(['submitted_at' => '2026-09-01 10:00:00'])->saveQuietly();
        $this->items($request, 603);
        $empty = $this->requisition($owner, ['grand_total' => 7]);
        $excluded = $this->requisition($owner, ['status' => 'withdrawn', 'grand_total' => 900]);
        $this->items($excluded, 1);
        $query = ['period' => 'custom', 'date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'dept' => 'CETE', 'tier' => 'minor', 'date_basis' => 'fulfilled'];
        $preview = $this->get(route('admin.reports.builder', [...$query, 'report_type' => 'requisitions']))->assertOk();
        $this->assertEquals(610, $preview->viewData('summaryValue'), 'Request totals must be counted once, not once per item.');

        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $rows = $this->csv($this->get(route('admin.reports.builder', [...$query, 'report_type' => 'requisitions', 'export' => 'csv']))->assertOk());
            $itemQueries = $this->selectsFrom('requisition_items');
        } finally {
            DB::disableQueryLog();
        }
        $this->assertCount(604, $rows);
        $this->assertCount(603, array_filter($rows, fn ($row) => $row['Tracking No.'] === $request->tracking_code));
        $this->assertSame('No line item', array_values(array_filter($rows, fn ($row) => $row['Tracking No.'] === $empty->tracking_code))[0]['Item']);
        $this->assertSame('2026-10-01 12:00', $rows[0]['Completed / Released At']);
        $this->assertGreaterThan(3, count($itemQueries));
        foreach ($itemQueries as $sql) {
            $this->assertMatchesRegularExpression('/limit 200\b/i', $sql);
        }

        $groups = $this->csv($this->get(route('admin.reports.builder', [...$query, 'report_type' => 'item_consumption', 'export' => 'csv']))->assertOk());
        $this->assertCount(603, $groups);
        $this->assertSame(array_map(fn ($i) => sprintf('Item %04d', $i), range(1, 603)), array_column($groups, 'Item'));
        $this->assertSame(array_fill(0, 603, '1'), array_column($groups, 'Requested Qty on Fulfilled Requests'));
        $departments = $this->csv($this->get(route('admin.reports.builder', [...$query, 'report_type' => 'dept_spending', 'export' => 'csv']))->assertOk());
        $this->assertSame('610', $departments[0]['Recorded Fulfilled Value']);
        $this->assertSame('2', $departments[0]['Fulfilled Requests (Completion Date)']);
    }

    public function test_every_report_csv_matches_its_normalized_preview_columns_and_values(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create(['department' => 'CETE', 'name' => '@Employee']);
        $request = $this->requisition($owner);
        $this->items($request, 1);
        $supply = Supply::create(['item_name' => '=Supply', 'quantity' => 2, 'unit_price' => 3, 'min_stock_level' => 5, 'unit' => 'Piece', 'brand' => 'Å Brand']);
        SupplyInventoryLog::create(['supply_id' => $supply->id, 'user_id' => $smo->id, 'requisition_id' => $request->id, 'transaction_type' => 'manual_adjustment', 'quantity_change' => -2, 'balance_after' => 2, 'remarks' => '+Adjustment']);
        ApprovalLog::create(['requisition_id' => $request->id, 'role' => 'dept_head', 'action' => 'Approved', 'remarks' => '-Remark']);
        $this->activities($owner, ['@Login']);
        $this->actingAs($smo);
        foreach (['requisitions', 'inventory', 'dept_spending', 'users', 'item_consumption', 'approval_audit', 'inventory_movements', 'user_activity'] as $type) {
            $query = ['report_type' => $type, 'period' => 'all'];
            $preview = $this->get(route('admin.reports.builder', $query))->assertOk();
            $columns = $preview->viewData('columns');
            $expected = $preview->viewData('rows')->map(function ($row) use ($columns) {
                $values = [];
                foreach ($columns as $column) {
                    $values[$column['label']] = $this->escapeCell((string) ($row[$column['key']] ?? ''));
                }

                return $values;
            })->all();
            $this->assertNotEmpty($expected, $type);
            $response = $this->get(route('admin.reports.builder', [...$query, 'export' => 'csv']))->assertOk();
            $this->assertMatchesRegularExpression('/HTC_SMO_'.$type.'_\d{8}_\d{6}\.csv/', $response->headers->get('Content-Disposition'));
            $this->assertSame($expected, $this->csv($response), $type);
        }
    }

    public function test_oversized_pdf_is_rejected_before_hydration_and_csv_still_exports_all_rows(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $owner = User::factory()->create(['department' => 'CETE']);
        $this->activities($owner, array_fill(0, ReportController::PDF_ROW_LIMIT + 1, 'Activity'));
        $this->actingAs($smo);
        Pdf::shouldReceive('loadView')->never();
        $query = ['report_type' => 'user_activity', 'period' => 'all', 'dept' => 'CETE'];
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->get(route('admin.reports.builder', [...$query, 'download_pdf' => 1]), ['Accept' => 'application/pdf', 'X-Requested-With' => 'XMLHttpRequest'])
                ->assertUnprocessable()->assertJsonPath('message', $this->pdfLimitMessage());
            // There must be no Eloquent hydration/relationship queries after the probe.
            $this->assertCount(1, $this->selectsFrom('user_activity_logs'));
            $this->assertStringContainsString('limit 501', $this->selectsFrom('user_activity_logs')[0]);
        } finally {
            DB::disableQueryLog();
        }
        $fallback = $this->get(route('admin.reports.builder', [...$query, 'download_pdf' => 1]))
            ->assertRedirect(route('admin.reports.builder', $query))->assertSessionHasErrors('download_pdf');
        $this->get($fallback->headers->get('Location'))->assertOk()->assertSee($this->pdfLimitMessage());
        $this->assertCount(501, $this->csv($this->get(route('admin.reports.builder', [...$query, 'export' => 'csv']))->assertOk()));
    }

    public function test_pdf_limit_counts_expanded_items_and_grouped_output_rather_than_source_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        $request = $this->requisition(User::factory()->create());
        $this->items($request, ReportController::PDF_ROW_LIMIT + 1, distinct: false);
        $this->getJson(route('admin.reports.builder', ['period' => 'all', 'download_pdf' => 1]))
            ->assertUnprocessable()->assertJsonPath('message', $this->pdfLimitMessage());
        // 501 source items produce one aggregate row, which is supported.
        $this->expectPdf(function ($data) {
            $this->assertCount(1, $data['rows']);
            $this->assertSame(501, $data['rows']->first()['quantity']);
            $this->assertEquals(501, $data['summaryValue']);
        });
        $this->get(route('admin.reports.builder', ['report_type' => 'item_consumption', 'period' => 'all', 'download_pdf' => 1]))->assertOk();
    }

    public function test_pdf_at_limit_keeps_full_summary_filters_order_and_filename(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        $owner = User::factory()->create(['department' => 'CETE']);
        $this->activities($owner, array_map(fn ($i) => 'Activity '.$i, range(1, ReportController::PDF_ROW_LIMIT)));
        $this->activities($owner, ['Outside range'], '2026-09-30 23:59:59');
        $this->expectPdf(function ($data) {
            $this->assertCount(500, $data['rows']);
            $this->assertSame(500, $data['summaryValue']);
            $this->assertSame('Recorded User Activities', $data['summaryLabel']);
            $this->assertNull($data['pagination']);
            $this->assertSame('Activity 500', $data['rows']->first()['action']);
            $this->assertSame('Activity 1', $data['rows']->last()['action']);
        });
        $this->get(route('admin.reports.builder', ['report_type' => 'user_activity', 'period' => 'custom', 'date_from' => '2026-10-01', 'date_to' => '2026-10-01', 'dept' => 'CETE', 'page' => 8, 'download_pdf' => 1]))->assertOk();
    }

    public function test_exports_remain_smo_only_and_empty_csv_keeps_headers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'employee']));
        foreach (['export' => 'csv', 'download_pdf' => 1] as $key => $value) {
            $this->get(route('admin.reports.builder', [$key => $value]))->assertRedirect(route('dashboard'));
        }
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        $response = $this->get(route('admin.reports.builder', ['report_type' => 'inventory_movements', 'period' => 'all', 'export' => 'csv']))->assertOk();
        $this->assertSame([], $this->csv($response));
        $this->assertStringContainsString('Quantity Change', $response->streamedContent());
    }

    private function requisition(User $owner, array $attributes = []): Requisition
    {
        return Requisition::create([...[
            'user_id' => $owner->id, 'request_type' => 'minor', 'status' => 'completed',
            'purpose' => 'Export test', 'grand_total' => 603, 'closed_at' => '2026-10-01 12:00:00',
        ], ...$attributes]);
    }

    private function items(Requisition $request, int $count, bool $distinct = true): void
    {
        foreach (array_chunk(range(1, $count), 100) as $chunk) {
            DB::table('requisition_items')->insert(array_map(fn ($i) => [
                'requisition_id' => $request->id, 'item_name' => $distinct ? sprintf('Item %04d', $i) : 'Same item',
                'quantity' => 1, 'unit' => 'Piece', 'unit_price' => 1, 'subtotal' => 1,
            ], $chunk));
        }
    }

    private function activities(User $owner, array $actions, string $date = '2026-10-01 12:00:00'): void
    {
        foreach (array_chunk($actions, 100) as $chunk) {
            UserActivityLog::insert(array_map(fn ($action) => [
                'user_id' => $owner->id, 'action' => $action, 'created_at' => $date,
                'ip_address' => '127.0.0.1', 'user_agent' => 'Test browser',
            ], $chunk));
        }
    }

    private function selectsFrom(string $table): array
    {
        return collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => preg_match('/^select .*from "'.$table.'"/i', $sql))->values()->all();
    }

    private function csv(TestResponse $response): array
    {
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertDownload();
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $headers = fgetcsv($stream);
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            $rows[] = array_combine($headers, $values);
        }
        fclose($stream);

        return $rows;
    }

    private function escapeCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function pdfLimitMessage(): string
    {
        return 'PDF reports support up to 500 rows (including requisition line items). Narrow the filters or export CSV for all matching records.';
    }

    private function expectPdf(callable $check): void
    {
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->withArgs(fn ($filename) => preg_match('/^HTC_SMO_\w+_\d{8}_\d{6}\.pdf$/', $filename) === 1)->andReturn(response('PDF fixture'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($check) {
            $this->assertSame('admin.reports.pdf_template', $view);
            $check($data);

            return true;
        })->andReturn($pdf);
    }
}
