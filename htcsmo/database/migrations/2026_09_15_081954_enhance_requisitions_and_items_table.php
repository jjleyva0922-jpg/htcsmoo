<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add tracking_number to requisitions table
        Schema::table('requisitions', function (Blueprint $table) {
            $table->string('tracking_number', 50)->nullable()->unique()->after('id');
            $table->text('purpose')->nullable()->after('status');
        });

        // 2. Add supply_id foreign key to requisition_items table
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->foreignId('supply_id')
                  ->nullable()
                  ->after('requisition_id')
                  ->constrained('supplies')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropForeign(['supply_id']);
            $table->dropColumn('supply_id');
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn(['tracking_number', 'purpose']);
        });
    }
};
