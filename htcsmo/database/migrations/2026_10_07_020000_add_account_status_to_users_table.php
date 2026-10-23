<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status', 20)->default('pending')->after('is_approved')->index();
            $table->unsignedInteger('account_version')->default(0)->after('account_status');
        });

        DB::table('users')->where('is_approved', true)->update(['account_status' => 'active']);
        DB::table('users')->where('is_approved', false)->update(['account_status' => 'pending']);

        DB::table('departments')->orderBy('id')->each(function ($department) {
            DB::table('users')->where('department_id', $department->id)
                ->update(['department' => $department->dept_code]);
            DB::table('users')->whereNull('department_id')->where('department', $department->dept_code)
                ->update(['department_id' => $department->id]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_status']);
            $table->dropColumn(['account_status', 'account_version']);
        });
    }
};
