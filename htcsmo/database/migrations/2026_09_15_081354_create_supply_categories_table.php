<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the lookup categories table
        Schema::create('supply_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Add foreign key column to supplies table safely
        Schema::table('supplies', function (Blueprint $table) {
            $table->foreignId('supply_category_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('supply_categories')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            $table->dropForeign(['supply_category_id']);
            $table->dropColumn('supply_category_id');
        });

        Schema::dropIfExists('supply_categories');
    }
};
