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
        // Safely drop the legacy single-item prototype tables
        Schema::dropIfExists('request_items');
        Schema::dropIfExists('supply_requests');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate only if rolled back
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('item_name');
            $table->timestamps();
        });

        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
