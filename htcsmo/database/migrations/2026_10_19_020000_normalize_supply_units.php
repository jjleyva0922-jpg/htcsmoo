<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_units', function (Blueprint $table): void {
            $table->id();
            $table->string('unit_name', 50)->unique();
            $table->string('abbreviation', 20)->nullable();
            $table->timestamps();
        });

        Schema::table('supplies', function (Blueprint $table): void {
            $table->foreignId('supply_unit_id')->nullable()->after('supply_category_id')
                ->constrained('supply_units')->nullOnDelete();
        });

        DB::table('supplies')->whereNotNull('unit')->where('unit', '<>', '')
            ->select(['id', 'unit'])->orderBy('id')->each(function ($supply): void {
                $unitName = trim($supply->unit);
                DB::table('supply_units')->insertOrIgnore([
                    'unit_name' => $unitName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('supplies')->where('id', $supply->id)->update([
                    'supply_unit_id' => DB::table('supply_units')->where('unit_name', $unitName)->value('id'),
                ]);
            });

        Schema::table('supplies', function (Blueprint $table): void {
            $table->dropColumn('unit');
        });
    }

    public function down(): void
    {
        Schema::table('supplies', function (Blueprint $table): void {
            $table->string('unit', 50)->nullable()->after('quantity');
        });

        DB::table('supply_units')->orderBy('id')->each(function ($unit): void {
            DB::table('supplies')->where('supply_unit_id', $unit->id)
                ->update(['unit' => $unit->unit_name]);
        });

        Schema::table('supplies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supply_unit_id');
        });
        Schema::dropIfExists('supply_units');
    }
};
