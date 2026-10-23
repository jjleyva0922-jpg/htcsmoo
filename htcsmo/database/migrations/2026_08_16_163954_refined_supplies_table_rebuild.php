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
            // 1. Drop the old table first so it doesn't give a "table already exists" error
            Schema::dropIfExists('supplies');

            // 2. Now recreate it cleanly with all columns
            Schema::create('supplies', function (Blueprint $table) {
                $table->id();
                $table->string('item_name')->unique();
                $table->string('brand')->nullable();
                $table->string('model_number')->nullable();
                $table->string('category');
                $table->text('specifications')->nullable();
                $table->integer('quantity')->default(0);
                $table->string('unit');
                $table->decimal('unit_price', 15, 2);
                $table->integer('min_stock_level')->default(5);
                $table->timestamps();
            });
        }

    public function down(): void
    {
       Schema::dropIfExists('supplies');
    }
};
