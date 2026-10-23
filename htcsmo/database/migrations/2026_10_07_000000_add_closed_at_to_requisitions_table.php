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
            $table->timestamp('closed_at')->nullable()->index()->after('status');
        });

        DB::table('requisitions')
            ->whereIn('status', ['released', 'rejected'])
            ->orderBy('id')
            ->chunkById(100, function ($requisitions): void {
                foreach ($requisitions as $requisition) {
                    $action = $requisition->status === 'released' ? '%Released%' : '%Rejected%';
                    $lifecycleDate = DB::table('approval_logs')
                        ->where('requisition_id', $requisition->id)
                        ->where('action', 'like', $action)
                        ->max('created_at');

                    DB::table('requisitions')
                        ->where('id', $requisition->id)
                        ->update(['closed_at' => $lifecycleDate ?: $requisition->updated_at ?: $requisition->created_at]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
