<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->text('specifications')->nullable()->after('item_name');
            $table->integer('quantity')->default(1)->after('specifications');
            $table->string('unit')->nullable()->after('quantity');
            $table->decimal('unit_price', 10, 2)->default(0)->after('unit');
            $table->decimal('total_amount', 10, 2)->default(0)->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            //
        });
    }
};
