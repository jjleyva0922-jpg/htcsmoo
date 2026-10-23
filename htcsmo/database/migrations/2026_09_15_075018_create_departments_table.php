<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('dept_code', 20)->unique(); // e.g. CETE, CTE, CBMA, CCJE, CAS
            $table->string('dept_name', 255);          // e.g. College of Engineering and Technology Education
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Add foreign key column to users table safely
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')
                  ->nullable()
                  ->after('school_id')
                  ->constrained('departments')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
