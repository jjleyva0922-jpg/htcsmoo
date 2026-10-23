<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_styles_are_in_the_head_before_page_content(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));

        foreach ([
            'dashboard' => '.dashboard-page {',
            'inventory.index' => '.inventory-workspace {',
            'requests.index' => '.request-history-table {',
            'notifications' => '.notification-center-page {',
            'profile.edit' => '.account-summary-card {',
            'admin.approvals' => '.approval-purpose {',
            'admin.reports.builder' => '.report-control-panel {',
        ] as $route => $selector) {
            $html = $this->get(route($route))->assertOk()->getContent();
            [$head, $body] = explode('</head>', $html, 2);

            $this->assertStringContainsString($selector, $head, $route);
            $this->assertStringNotContainsString($selector, $body, $route);
            if ($route === 'requests.index') {
                $this->assertSame(1, substr_count($head, $selector), 'Shared table styles should appear once.');
            }
            $this->assertStringContainsString('[x-cloak], [hidden] { display: none !important; }', $head);
        }
    }

    public function test_guest_styles_are_also_available_before_the_content(): void
    {
        $html = $this->get(route('legal.privacy'))->assertOk()->getContent();
        [$head, $body] = explode('</head>', $html, 2);

        $this->assertStringContainsString('.legal-page {', $head);
        $this->assertStringNotContainsString('.legal-page {', $body);
        $this->assertStringContainsString('[x-cloak], [hidden] { display: none !important; }', $head);
    }

    public function test_report_date_fields_have_the_correct_visibility_before_javascript_runs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));

        foreach (['all', 'custom'] as $period) {
            $html = $this->get(route('admin.reports.builder', [
                'period' => $period,
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
            ]))->assertOk()->getContent();
            $document = new DOMDocument;
            @$document->loadHTML($html);
            $fields = (new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " custom-date-field ")]');

            $this->assertCount(2, $fields);
            foreach ($fields as $field) {
                $this->assertSame($period !== 'custom', $field->hasAttribute('hidden'));
                $this->assertSame($period !== 'custom', $field->getElementsByTagName('input')->item(0)->hasAttribute('disabled'));
            }
        }
    }

    public function test_live_workspace_fragments_reuse_the_styles_loaded_with_the_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'smo']));

        $this->get(route('inventory.index'), ['X-Workspace-Refresh' => 'true'])
            ->assertOk()
            ->assertSee('data-live-region="workspace"', false)
            ->assertDontSee('<style>', false)
            ->assertDontSee('<head>', false);
    }

    public function test_dashboard_totals_are_rendered_with_their_values_before_javascript_and_on_refresh(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        Requisition::create([
            'user_id' => $user->id,
            'request_type' => 'minor',
            'status' => 'pending',
            'purpose' => 'Stable initial render fixture',
            'grand_total' => 100,
        ]);
        $this->actingAs($user);

        foreach ([[], ['X-Workspace-Refresh' => 'true']] as $headers) {
            $this->get(route('dashboard'), $headers)->assertOk()
                ->assertSee('x-data="countUp(1)" x-text="current">1</strong>', false);
        }
    }
}
