<?php

namespace Tests\Feature;

use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_separates_tiers_and_prioritizes_low_stock(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);

        $healthy = $this->supply('Healthy Paper', 'minor', 40, 5);
        $major = $this->supply('Projector', 'major', 3, 1, 25000);
        $low = $this->supply('Low Ink', 'minor', 2, 5);
        $out = $this->supply('Empty Toner', 'minor', 0, 5);

        $response = $this->actingAs($smo)->get(route('inventory.index'));

        $response->assertOk()
            ->assertSee('<title>Manage Stock</title>', false)
            ->assertSee('Restock priority')
            ->assertSee('Minor catalog')
            ->assertSee('Major catalog')
            ->assertSeeInOrder([$out->item_name, $low->item_name, $healthy->item_name])
            ->assertSee($major->item_name);
    }

    public function test_smo_registers_brands_as_requestor_choices_without_a_brand_field(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);

        $response = $this->actingAs($smo)->post(route('inventory.store'), [
            'item_name' => 'Ballpoint Pen',
            'category' => 'Office Supplies',
            'requisition_tier' => 'minor',
            'specifications' => 'Retractable writing pen',
            'quantity' => 100,
            'unit' => 'Piece(s)',
            'unit_price' => 12,
            'min_stock_level' => 20,
            'option_names' => ['Brand', 'Ink Color', 'Tip Size'],
            'option_values' => ['Panda, Pilot, HBW', 'Black, Blue, Red', '0.5 mm, 0.7 mm'],
        ]);

        $response->assertRedirect(route('inventory.index'))
            ->assertSessionHasNoErrors();

        $supply = Supply::where('item_name', 'Ballpoint Pen')->firstOrFail();

        $this->assertSame('Panda, Pilot, HBW', $supply->brand);
        $this->assertSame([
            'Brand' => ['Panda', 'Pilot', 'HBW'],
            'Ink Color' => ['Black', 'Blue', 'Red'],
            'Tip Size' => ['0.5 mm', '0.7 mm'],
        ], $supply->configurable_options);
    }

    public function test_smo_can_generate_monthly_or_quarterly_forecast_reports(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $this->supply('Forecast Paper', 'minor', 0, 10);

        $this->actingAs($smo)
            ->get(route('inventory.index', ['forecast_period' => 'quarterly']))
            ->assertOk()
            ->assertSee('Quarterly', false)
            ->assertSee(route('inventory.forecast.report', ['period' => 'quarterly']), false);

        $this->actingAs($smo)
            ->get(route('inventory.forecast.report', ['period' => 'monthly']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();
    }

    public function test_smo_can_delete_multiple_selected_inventory_items(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);
        $first = $this->supply('Selected Paper', 'minor', 10, 2);
        $second = $this->supply('Selected Toner', 'major', 5, 1);
        $kept = $this->supply('Kept Stapler', 'minor', 8, 2);

        $this->actingAs($smo)
            ->delete(route('inventory.bulk-destroy'), ['ids' => [$first->id, $second->id]])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHas('success', '2 inventory items have been removed.');

        $this->assertSoftDeleted('supplies', ['id' => $first->id]);
        $this->assertSoftDeleted('supplies', ['id' => $second->id]);
        $this->assertDatabaseHas('supplies', ['id' => $kept->id]);
    }

    public function test_bulk_delete_requires_at_least_one_valid_inventory_item(): void
    {
        $smo = User::factory()->create(['role' => 'smo']);

        $this->actingAs($smo)
            ->from(route('inventory.index'))
            ->delete(route('inventory.bulk-destroy'), ['ids' => []])
            ->assertRedirect(route('inventory.index'))
            ->assertSessionHasErrors('ids');
    }

    private function supply(string $name, string $tier, int $quantity, int $minimum, float $price = 100): Supply
    {
        return Supply::create([
            'item_name' => $name,
            'brand' => 'HTC Test',
            'category' => 'Office Supplies',
            'requisition_tier' => $tier,
            'specifications' => 'Standard specification',
            'quantity' => $quantity,
            'unit' => 'Piece(s)',
            'unit_price' => $price,
            'min_stock_level' => $minimum,
        ]);
    }
}
