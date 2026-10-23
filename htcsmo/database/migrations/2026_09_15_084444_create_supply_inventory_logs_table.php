<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->constrained('supplies')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // The officer responsible
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();

            $table->string('transaction_type', 50); // 'release_deduction', 'restock_delivery', 'manual_adjustment'
            $table->integer('quantity_change');     // e.g. -5, +50
            $table->integer('balance_after');       // stock level after change
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_inventory_logs');
    }
};
