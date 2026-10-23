<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Requisition;
use App\Models\Supply;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicSearchWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_server_backed_search_form_uses_dynamic_search(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);

        foreach ([
            'requests.index',
            'requests.archive',
            'admin.archive',
            'admin.users.pending',
            'admin.users.manage',
            'inventory.index',
            'admin.reports.index',
            'admin.reports.builder',
            'admin.approvals',
        ] as $routeName) {
            $this
                ->actingAs($smo)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('data-dynamic-search', false);
        }
    }

    public function test_filter_forms_have_get_fallbacks_and_unique_result_regions_without_automatic_actions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        foreach (['requests.index', 'requests.archive', 'admin.archive', 'inventory.index', 'admin.users.pending',
            'admin.users.manage', 'admin.reports.index', 'admin.reports.builder', 'admin.approvals', 'notifications'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $document = new DOMDocument;
            @$document->loadHTML($html);
            $xpath = new DOMXPath($document);
            $root = $xpath->query('//*[@data-filter-workspace]')->item(0);
            $this->assertNotNull($root, $route);
            $this->assertGreaterThan(0, $xpath->query('.//*[@data-filter-results and @tabindex="-1"]', $root)->length, $route);
            foreach ($xpath->query('.//form[@data-dynamic-search]', $root) as $form) {
                $this->assertSame('get', strtolower($form->getAttribute('method')), $route);
                $this->assertNotEmpty($form->getAttribute('id'), $route);
                $this->assertStringContainsString('#', $form->getAttribute('action'), $route);
                $this->assertGreaterThan(0, $xpath->query('.//noscript//button[@type="submit"]', $form)->length, $route);
                $this->assertSame(0, $xpath->query('.//button[@type="submit" and not(@name) and not(ancestor::noscript)]', $form)->length, $route);
            }
            $names = [];
            foreach ($xpath->query('.//*[@data-filter-region]', $root) as $region) {
                $names[] = $region->getAttribute('data-filter-region');
                $this->assertSame(0, $xpath->query('.//*[@data-filter-region]', $region)->length, 'Result regions must not be nested: '.$route);
            }
            $this->assertSame($names, array_values(array_unique($names)), $route);
            $this->assertSame(0, $xpath->query('//form[translate(@method,"POST","post")="post"][@data-dynamic-search]')->length);
        }
    }

    public function test_filter_errors_are_json_without_redirecting_or_rendering_old_results(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        foreach (['requests.index', 'requests.archive', 'admin.archive', 'admin.reports.index', 'admin.reports.builder'] as $route) {
            $this->get(route($route, ['period' => 'custom', 'date_from' => '2026-10-22', 'date_to' => '2026-10-01']),
                ['Accept' => 'text/html', 'X-Requested-With' => 'XMLHttpRequest', 'X-Filter-Request' => 'true'])
                ->assertUnprocessable()->assertJsonValidationErrors('date_to');
        }
        $this->get(route('admin.reports.builder', ['period' => 'custom', 'date_from' => '2026-10-01']),
            ['X-Filter-Request' => 'true'])->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }

    public function test_html_filter_updates_keep_employee_requests_and_archives_private(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownActive = $this->requisition($owner, 'pending');
        $ownArchive = $this->requisition($owner, 'completed');
        $privateActive = $this->requisition($other, 'pending');
        $privateArchive = $this->requisition($other, 'completed');
        $headers = ['Accept' => 'text/html', 'X-Filter-Request' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];
        $this->actingAs($owner)->get(route('requests.index', ['search' => 'Shared filter marker', 'status' => 'pending']), $headers)
            ->assertOk()->assertSee($ownActive->tracking_code)->assertDontSee($privateActive->tracking_code)
            ->assertSee('data-result-count="1"', false);
        $this->get(route('requests.archive', ['search' => 'Shared filter marker']), $headers)
            ->assertOk()->assertSee($ownArchive->tracking_code)->assertDontSee($privateArchive->tracking_code)
            ->assertSee('data-result-count="1"', false);
        $this->get(route('requests.archive', ['dept' => 'CETE']), $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('dept');
        $this->get(route('requests.index', ['dept' => 'CETE']), $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('dept');
        foreach (['admin.archive', 'inventory.index', 'admin.users.pending', 'admin.users.manage', 'admin.reports.index', 'admin.reports.builder'] as $route) {
            $this->get(route($route), $headers)->assertRedirect('/dashboard');
        }
    }

    public function test_active_details_have_a_validated_local_return_link_with_query_and_results_anchor(): void
    {
        $owner = User::factory()->create();
        $record = $this->requisition($owner, 'pending');
        $filters = ['search' => 'Shared filter marker', 'status' => 'pending', 'order' => 'asc', 'page' => 2];
        $this->actingAs($owner)->get(route('requests.show', [$record, 'from_list' => 1, 'list_filters' => $filters]))
            ->assertOk()->assertViewHas('activeReturnUrl', route('requests.index', $filters).'#request-results')
            ->assertSee('Back to active results');
        $this->get(route('requests.show', [$record, 'from_list' => 1, 'list_filters' => ['status' => 'fake']]))
            ->assertOk()->assertViewHas('activeReturnUrl', null);
        $this->get(route('requests.show', [$record, 'from_list' => 1, 'list_filters' => 'https://evil.test']))
            ->assertOk()->assertViewHas('activeReturnUrl', null);
    }

    public function test_inventory_filters_work_in_the_server_rendered_catalog_and_preserve_the_forecast_dataset(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        foreach ([['Matching paper', 2], ['Healthy paper', 20], ['Matching toner', 0]] as [$name, $quantity]) {
            Supply::create(['item_name' => $name, 'category' => 'Office Supplies', 'requisition_tier' => 'minor',
                'quantity' => $quantity, 'unit' => 'Piece', 'unit_price' => 10, 'min_stock_level' => 5]);
        }
        $response = $this->get(route('inventory.index', ['search' => 'paper', 'stock' => 'attention', 'forecast_period' => 'quarterly']));
        $response->assertOk()->assertViewHas('catalogSupplies', fn ($items) => $items->pluck('item_name')->all() === ['Matching paper'])
            ->assertViewHas('supplies', fn ($items) => $items->count() === 3)->assertSee('data-result-count="1"', false);
        $this->get(route('inventory.index', ['search' => 'missing']))->assertOk()
            ->assertViewHas('catalogSupplies', fn ($items) => $items->isEmpty())->assertSee('No matching minor items');
        $this->get(route('inventory.index', ['stock' => 'bad']), ['X-Filter-Request' => 'true'])
            ->assertUnprocessable()->assertJsonValidationErrors('stock');
    }

    public function test_personnel_and_notifications_preserve_department_and_recipient_scopes_when_filtered(): void
    {
        $head = User::factory()->create(['role' => 'dept_head', 'department' => 'CETE']);
        $own = User::factory()->create(['name' => 'Filter Match Own', 'department' => 'CETE']);
        $other = User::factory()->create(['name' => 'Filter Match Private', 'department' => 'CAS']);
        Notification::create(['user_id' => $head->id, 'title' => 'Personal update', 'message' => 'Mine', 'event_type' => 'account.updated']);
        Notification::create(['user_id' => $other->id, 'title' => 'Private update', 'message' => 'Private', 'event_type' => 'account.updated']);
        $headers = ['Accept' => 'text/html', 'X-Requested-With' => 'XMLHttpRequest', 'X-Filter-Request' => 'true'];
        $this->actingAs($head)->get(route('admin.users.manage', ['category' => 'staff', 'search' => 'Filter Match']), $headers)
            ->assertOk()->assertSee($own->email)->assertDontSee($other->email)->assertSee('data-result-count="1"', false);
        $this->get(route('notifications', ['category' => 'accounts']), $headers)->assertOk()
            ->assertSee('Personal update')->assertDontSee('Private update');
        $this->get(route('notifications', ['category' => 'inventory']), $headers)->assertForbidden();
    }

    public function test_report_exports_remain_explicit_and_report_type_switches_use_the_full_page_fallback(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));
        $this->get(route('admin.reports.builder'))->assertOk()->assertSee('data-filter-navigate', false)
            ->assertSee('name="export" value="csv"', false)->assertSee('id="downloadPdfButton"', false)
            ->assertDontSee('onchange="this.form.submit()"', false)->assertDontSee('Refresh Preview');
        $this->get(route('admin.reports.builder', ['report_type' => 'users', 'period' => 'all', 'export' => 'csv']))
            ->assertOk()->assertDownload()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    private function requisition(User $owner, string $status): Requisition
    {
        return Requisition::create(['user_id' => $owner->id, 'status' => $status,
            'request_type' => 'minor', 'purpose' => 'Shared filter marker', 'grand_total' => 100]);
    }
}
