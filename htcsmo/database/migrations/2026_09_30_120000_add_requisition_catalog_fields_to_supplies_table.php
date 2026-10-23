<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            $table->string('requisition_tier', 10)->default('minor')->after('category');
            $table->json('configurable_options')->nullable()->after('specifications');
        });

        // Sensible first-time classification; SMO can override it from inventory.
        DB::table('supplies')->where('unit_price', '>', 1000)->update(['requisition_tier' => 'major']);
    }

    public function down(): void
    {
        Schema::table('supplies', function (Blueprint $table) {
            $table->dropColumn(['requisition_tier', 'configurable_options']);
        });
    }
};
