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
        Schema::table('supplies', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('item_name');
            $table->string('model_number')->nullable()->after('brand');
            $table->string('category')->after('model_number'); // e.g., Office Supplies, IT, Furniture
            $table->integer('min_stock_level')->default(5)->after('quantity'); // Alert threshold
            $table->text('physical_description')->nullable(); // Size, Color, Weight
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            //
        });
    }
};
