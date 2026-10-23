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
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('item_name'); // For the "Quick View" item name
            $table->enum('request_type', ['minor', 'major']);
            $table->enum('status', ['pending', 'approved', 'rejected', 'released'])->default('pending');
            $table->dateTime('approved_at')->nullable(); // For the "last 24hrs" card
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_requests');
    }
};
