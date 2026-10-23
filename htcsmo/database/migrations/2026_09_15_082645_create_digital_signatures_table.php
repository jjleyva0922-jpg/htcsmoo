<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_log_id')
                  ->unique() // 1:1 Relationship
                  ->constrained('approval_logs')
                  ->onDelete('cascade');

            $table->string('signature_token', 255)->unique(); // SHA-256 Hash
            $table->string('signer_name', 255);
            $table->string('signer_role', 50);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('signed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_signatures');
    }
};
