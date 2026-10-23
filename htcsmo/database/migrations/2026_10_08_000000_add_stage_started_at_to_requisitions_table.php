<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->timestamp('stage_started_at')->nullable()->after('status');
            $table->index(['status', 'stage_started_at']);
        });

        DB::table('requisitions')->update([
            'stage_started_at' => DB::raw('COALESCE(updated_at, created_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropIndex(['status', 'stage_started_at']);
            $table->dropColumn('stage_started_at');
        });
    }
};
