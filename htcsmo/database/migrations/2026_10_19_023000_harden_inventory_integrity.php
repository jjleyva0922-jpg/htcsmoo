<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $invalidCounts = [
            'supplies' => DB::table('supplies')->where('quantity', '<', 0)
                ->orWhere('unit_price', '<', 0)->orWhere('min_stock_level', '<', 0)->count(),
            'requisition_items' => DB::table('requisition_items')->where('quantity', '<=', 0)
                ->orWhere('unit_price', '<', 0)->orWhere('subtotal', '<', 0)->count(),
            'requisitions' => DB::table('requisitions')->where('grand_total', '<', 0)->count(),
            'inventory_logs' => DB::table('supply_inventory_logs')->where('quantity_change', 0)
                ->orWhere('balance_after', '<', 0)->count(),
        ];

        if (array_sum($invalidCounts) > 0) {
            throw new RuntimeException('Inventory integrity migration stopped because invalid numeric values exist: '.json_encode($invalidCounts));
        }

        Schema::table('supplies', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('supply_inventory_logs', function (Blueprint $table): void {
            $table->dropForeign(['supply_id']);
            $table->foreign('supply_id')->references('id')->on('supplies')->restrictOnDelete();
            $table->index(['supply_id', 'created_at'], 'inventory_logs_supply_created_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE supplies ADD CONSTRAINT supplies_quantity_nonnegative CHECK (quantity >= 0)');
            DB::statement('ALTER TABLE supplies ADD CONSTRAINT supplies_price_nonnegative CHECK (unit_price >= 0)');
            DB::statement('ALTER TABLE supplies ADD CONSTRAINT supplies_min_stock_nonnegative CHECK (min_stock_level >= 0)');
            DB::statement('ALTER TABLE requisition_items ADD CONSTRAINT req_items_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE requisition_items ADD CONSTRAINT req_items_price_nonnegative CHECK (unit_price >= 0)');
            DB::statement('ALTER TABLE requisition_items ADD CONSTRAINT req_items_subtotal_nonnegative CHECK (subtotal >= 0)');
            DB::statement('ALTER TABLE requisitions ADD CONSTRAINT requisitions_total_nonnegative CHECK (grand_total >= 0)');
            DB::statement('ALTER TABLE supply_inventory_logs ADD CONSTRAINT inventory_change_nonzero CHECK (quantity_change <> 0)');
            DB::statement('ALTER TABLE supply_inventory_logs ADD CONSTRAINT inventory_balance_nonnegative CHECK (balance_after >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE supply_inventory_logs DROP CHECK inventory_balance_nonnegative');
            DB::statement('ALTER TABLE supply_inventory_logs DROP CHECK inventory_change_nonzero');
            DB::statement('ALTER TABLE requisitions DROP CHECK requisitions_total_nonnegative');
            DB::statement('ALTER TABLE requisition_items DROP CHECK req_items_subtotal_nonnegative');
            DB::statement('ALTER TABLE requisition_items DROP CHECK req_items_price_nonnegative');
            DB::statement('ALTER TABLE requisition_items DROP CHECK req_items_quantity_positive');
            DB::statement('ALTER TABLE supplies DROP CHECK supplies_min_stock_nonnegative');
            DB::statement('ALTER TABLE supplies DROP CHECK supplies_price_nonnegative');
            DB::statement('ALTER TABLE supplies DROP CHECK supplies_quantity_nonnegative');
        }

        Schema::table('supply_inventory_logs', function (Blueprint $table): void {
            $table->dropIndex('inventory_logs_supply_created_index');
            $table->dropForeign(['supply_id']);
            $table->foreign('supply_id')->references('id')->on('supplies')->cascadeOnDelete();
        });

        Schema::table('supplies', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
