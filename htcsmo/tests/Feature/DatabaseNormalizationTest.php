<?php

namespace Tests\Feature;

use App\Models\Supply;
use App\Models\SupplyCategory;
use App\Models\SupplyInventoryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_redundant_catalog_and_account_columns_are_removed(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'department'));
        $this->assertFalse(Schema::hasColumn('users', 'is_approved'));
        $this->assertFalse(Schema::hasColumn('supplies', 'category'));
        $this->assertFalse(Schema::hasColumn('supplies', 'unit'));
        $this->assertFalse(Schema::hasColumn('supplies', 'brand'));
        $this->assertFalse(Schema::hasColumn('supplies', 'configurable_options'));

        $this->assertTrue(Schema::hasColumn('supplies', 'supply_category_id'));
        $this->assertTrue(Schema::hasColumn('supplies', 'supply_unit_id'));
        $this->assertTrue(Schema::hasColumn('approval_logs', 'acted_by_user_id'));
    }

    public function test_catalog_compatibility_values_are_stored_in_normalized_tables(): void
    {
        $category = SupplyCategory::where('category_name', 'Office Supplies')->firstOrFail();
        $supply = Supply::create([
            'supply_category_id' => $category->id,
            'item_name' => 'Normalization Test Paper',
            'brand' => 'PaperOne, Double A',
            'model_number' => null,
            'requisition_tier' => 'minor',
            'specifications' => '80 GSM',
            'configurable_options' => ['Size' => ['A4', 'Legal']],
            'quantity' => 10,
            'unit' => 'Ream',
            'unit_price' => 250,
            'min_stock_level' => 2,
        ]);

        $this->assertSame('Ream', $supply->fresh()->unit);
        $this->assertSame([
            'Brand' => ['PaperOne', 'Double A'],
            'Size' => ['A4', 'Legal'],
        ], $supply->fresh()->requestOptions());
        $this->assertDatabaseHas('supply_units', ['unit_name' => 'Ream']);
        $this->assertDatabaseHas('supply_options', ['supply_id' => $supply->id, 'name' => 'Brand']);
    }

    public function test_is_approved_is_derived_from_account_status(): void
    {
        $user = User::factory()->create(['account_status' => User::ACCOUNT_ACTIVE]);
        $this->assertTrue($user->is_approved);

        $user->update(['account_status' => User::ACCOUNT_SUSPENDED]);
        $this->assertFalse($user->fresh()->is_approved);
    }

    public function test_retiring_a_supply_preserves_its_inventory_history(): void
    {
        $user = User::factory()->create();
        $supply = Supply::create([
            'item_name' => 'Retired Inventory Item',
            'category' => 'Office Supplies',
            'requisition_tier' => 'minor',
            'specifications' => 'Audit retention test',
            'quantity' => 5,
            'unit' => 'Piece',
            'unit_price' => 10,
            'min_stock_level' => 1,
        ]);
        $log = SupplyInventoryLog::create([
            'supply_id' => $supply->id,
            'user_id' => $user->id,
            'transaction_type' => 'manual_adjustment',
            'quantity_change' => 5,
            'balance_after' => 5,
            'remarks' => 'Opening balance',
        ]);

        $supply->delete();

        $this->assertSoftDeleted('supplies', ['id' => $supply->id]);
        $this->assertDatabaseHas('supply_inventory_logs', ['id' => $log->id]);
        $this->assertSame($supply->id, $log->fresh()->supply?->id);
    }
}
