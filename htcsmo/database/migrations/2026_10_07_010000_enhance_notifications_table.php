<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('event_type', 80)->nullable()->after('type')->index();
            $table->string('action_type', 40)->nullable()->after('event_type');
            $table->unsignedBigInteger('action_id')->nullable()->after('action_type');
            $table->string('dedupe_key', 191)->nullable()->after('action_id');
            $table->timestamp('read_at')->nullable()->after('is_read');

            $table->unique(['user_id', 'dedupe_key'], 'notifications_user_dedupe_unique');
            $table->index(['user_id', 'is_read', 'id'], 'notifications_user_unread_id_index');
            $table->index(['action_type', 'action_id'], 'notifications_action_index');
        });

        DB::table('notifications')
            ->where('is_read', true)
            ->whereNull('read_at')
            ->update(['read_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_user_dedupe_unique');
            $table->dropIndex('notifications_user_unread_id_index');
            $table->dropIndex('notifications_action_index');
            $table->dropIndex(['event_type']);
            $table->dropColumn(['event_type', 'action_type', 'action_id', 'dedupe_key', 'read_at']);
        });
    }
};
