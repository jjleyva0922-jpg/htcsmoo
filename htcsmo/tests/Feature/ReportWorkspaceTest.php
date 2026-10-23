<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_smo_can_view_operational_analytics_on_its_own_page(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);

        $this
            ->actingAs($smo)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Operational Analytics')
            ->assertSee('Open Report Center')
            ->assertDontSee('Comprehensive Report Center');
    }

    public function test_smo_can_open_the_comprehensive_report_center_separately(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);

        $this
            ->actingAs($smo)
            ->get(route('admin.reports.builder'))
            ->assertOk()
            ->assertSee('Comprehensive Report Center')
            ->assertSee('Requisition Transactions')
            ->assertSee('id="downloadPdfButton"', false)
            ->assertSee('id="pdfGenerationModal"', false)
            ->assertSee('Generating PDF report…')
            ->assertSee('new AbortController()', false)
            ->assertSee("document.addEventListener('DOMContentLoaded', initializeReportBuilder", false)
            ->assertSee('if (cancelPending) closePdfModal()', false)
            ->assertDontSee('Fulfillment Volume');
    }

    public function test_old_analytics_bookmark_redirects_to_operational_analytics(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);

        $this->actingAs($smo)
            ->get(route('admin.reports.analytics'))
            ->assertRedirect('/admin/reports');
    }

    public function test_each_report_type_renders_inside_the_report_center(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);

        foreach ([
            'requisitions',
            'inventory',
            'dept_spending',
            'users',
            'item_consumption',
            'approval_audit',
            'inventory_movements',
            'user_activity',
        ] as $reportType) {
            $this
                ->actingAs($smo)
                ->get(route('admin.reports.builder', ['report_type' => $reportType]))
                ->assertOk()
                ->assertSee('matching records');
        }
    }

    public function test_every_report_type_can_be_exported_as_a_pdf(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);

        foreach ([
            'requisitions',
            'inventory',
            'dept_spending',
            'users',
            'item_consumption',
            'approval_audit',
            'inventory_movements',
            'user_activity',
        ] as $reportType) {
            $this
                ->actingAs($smo)
                ->get(route('admin.reports.builder', [
                    'report_type' => $reportType,
                    'download_pdf' => 1,
                ]))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_minor_request_pdf_supports_catalog_category_registers(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['department' => 'CETE']);
        $supply = Supply::create([
            'item_name' => 'A4 Bond Paper',
            'category' => 'Bond Paper',
            'requisition_tier' => 'minor',
            'quantity' => 25,
            'unit' => 'Ream',
            'unit_price' => 245,
            'min_stock_level' => 5,
        ]);
        $requisition = Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'purpose' => 'Office printing',
            'grand_total' => 490,
        ]);
        RequisitionItem::create([
            'requisition_id' => $requisition->id,
            'supply_id' => $supply->id,
            'item_name' => $supply->item_name,
            'specifications' => '80 gsm, white',
            'quantity' => 2,
            'unit' => 'Ream',
            'unit_price' => 245,
            'subtotal' => 490,
        ]);

        $response = $this
            ->actingAs($smo)
            ->get(route('admin.reports.builder', [
                'report_type' => 'requisitions',
                'tier' => 'minor',
                'period' => 'all',
                'download_pdf' => 1,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();

        $pdfContent = $response->getContent();
        $this->assertSame(
            1,
            preg_match_all('/\/Type\s*\/Page\b/', $pdfContent),
            'A one-request PDF should not contain a separate cover or blank page.',
        );
    }

    public function test_excel_compatible_csv_export_works(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);

        $this
            ->actingAs($smo)
            ->get(route('admin.reports.builder', [
                'report_type' => 'users',
                'period' => 'all',
                'export' => 'csv',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload();
    }

    public function test_department_spending_report_calculates_semester_costs_and_requestors(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $requestor = User::factory()->create(['department' => 'CETE']);

        Requisition::create([
            'user_id' => $requestor->id,
            'request_type' => 'major',
            'status' => 'released',
            'purpose' => 'Laboratory supplies',
            'grand_total' => 1500,
        ]);

        $this
            ->actingAs($smo)
            ->get(route('admin.reports.builder', [
                'report_type' => 'dept_spending',
                'period' => 'current_semester',
            ]))
            ->assertOk()
            ->assertSee('Department Fulfillment Value')
            ->assertSee('CETE')
            ->assertSee('₱1,500.00')
            ->assertSee('Active Requestors');
    }
}
