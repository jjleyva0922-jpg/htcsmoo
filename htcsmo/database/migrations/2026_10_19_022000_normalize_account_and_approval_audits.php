<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_logs', function (Blueprint $table): void {
            $table->foreignId('acted_by_user_id')->nullable()->after('requisition_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_approved');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_approved')->default(false)->after('role');
        });
        DB::table('users')->where('account_status', 'active')->update(['is_approved' => true]);

        Schema::table('approval_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('acted_by_user_id');
        });
    }
};
