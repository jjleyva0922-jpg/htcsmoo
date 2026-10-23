<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track only values changed here so rollback cannot erase later closures.
        if (! Schema::hasTable('requisition_closure_backfills')) {
            Schema::create('requisition_closure_backfills', function (Blueprint $table) {
                $table->unsignedBigInteger('requisition_id')->primary();
                $table->unsignedBigInteger('approval_log_id');
                $table->timestamp('closed_at');
            });
        }

        DB::table('requisitions')->where('status', 'completed')->whereNull('closed_at')
            ->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    // Ready-for-pickup is stock preparation, not completed handover.
                    $event = DB::table('approval_logs')->where('requisition_id', $row->id)
                        ->where('action', 'Completed by SMO In-Charge')->whereNotNull('created_at')
                        ->orderByDesc('created_at')->orderByDesc('id')->first();
                    if (! $event) {
                        continue;
                    }
                    DB::transaction(function () use ($row, $event) {
                        $changed = DB::table('requisitions')->where('id', $row->id)
                            ->where('status', 'completed')->whereNull('closed_at')->update(['closed_at' => $event->created_at]);
                        if ($changed) {
                            DB::table('requisition_closure_backfills')->insert([
                                'requisition_id' => $row->id, 'approval_log_id' => $event->id, 'closed_at' => $event->created_at,
                            ]);
                        }
                    });
                }
            });
    }

    public function down(): void
    {
        DB::table('requisition_closure_backfills')->orderBy('requisition_id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('requisitions')->where('id', $row->requisition_id)->where('status', 'completed')
                    ->where('closed_at', $row->closed_at)->update(['closed_at' => null]);
            }
        });
        Schema::dropIfExists('requisition_closure_backfills');
    }
};
