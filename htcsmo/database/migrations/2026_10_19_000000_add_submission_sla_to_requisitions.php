<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->timestamp('sla_warning_at')->nullable()->after('submitted_at');
            $table->timestamp('due_at')->nullable()->after('sla_warning_at');
            $table->index(['status', 'due_at']);
        });

        DB::table('requisitions')
            ->orderBy('id')
            ->chunkById(100, function ($requisitions): void {
                foreach ($requisitions as $requisition) {
                    $submittedAt = Carbon::parse($requisition->created_at);

                    DB::table('requisitions')
                        ->where('id', $requisition->id)
                        ->update([
                            'submitted_at' => $submittedAt,
                            'sla_warning_at' => $this->addWeekdays($submittedAt, 2),
                            'due_at' => $this->addWeekdays($submittedAt, 3),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropIndex(['status', 'due_at']);
            $table->dropColumn(['submitted_at', 'sla_warning_at', 'due_at']);
        });

        Schema::dropIfExists('institutional_holidays');
    }

    private function addWeekdays(Carbon $start, int $days): Carbon
    {
        $date = $start->copy();

        while ($days > 0) {
            $date->addDay();
            if (! $date->isWeekend()) {
                $days--;
            }
        }

        return $date;
    }
};
