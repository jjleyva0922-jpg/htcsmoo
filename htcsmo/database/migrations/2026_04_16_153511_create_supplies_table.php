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
        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->text('specifications')->nullable(); // e.g., "80gsm, Long"
            $table->integer('quantity')->default(0);    // Current stock level
            $table->string('unit');                      // e.g., "Ream", "Box", "Piece"
            $table->decimal('unit_price', 10, 2);       // To track costs
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
