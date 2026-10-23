<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. User Security & Activity Logs
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('action', 255);       // e.g. "User Logged In", "Updated Profile", "Submitted Requisition"
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 2. Outbound Institutional Email Notification Logs
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email', 255);
            $table->string('subject', 255);
            $table->string('status', 50)->default('sent'); // 'sent', 'failed', 'queued'
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('user_activity_logs');
    }
};
