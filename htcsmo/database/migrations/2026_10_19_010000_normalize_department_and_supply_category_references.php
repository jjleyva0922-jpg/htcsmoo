<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('departments')->insertOrIgnore(collect([
            ['dept_code' => 'CETE', 'dept_name' => 'College of Engineering and Technology Education'],
            ['dept_code' => 'CTE', 'dept_name' => 'College of Teacher Education'],
            ['dept_code' => 'CBMA', 'dept_name' => 'College of Business Management and Accountancy'],
            ['dept_code' => 'CCJE', 'dept_name' => 'College of Criminal Justice Education'],
            ['dept_code' => 'CAS', 'dept_name' => 'College of Arts and Sciences'],
        ])->map(fn (array $department) => $department + ['created_at' => $now, 'updated_at' => $now])->all());

        DB::table('supply_categories')->insertOrIgnore(collect([
            ['category_name' => 'Cleaning Supplies', 'description' => 'Sanitation, hygiene chemicals, soaps, and janitorial consumables.'],
            ['category_name' => 'Construction Supplies', 'description' => 'Plywood, cement, masonry, lumber, and campus building materials.'],
            ['category_name' => 'Drugs and Medicines', 'description' => 'Clinic pharmaceutical supplies, analgesics, and first-aid medications.'],
            ['category_name' => 'Maintenance Supplies', 'description' => 'Electrical, hardware, lighting, fixtures, and campus upkeep items.'],
            ['category_name' => 'Medical Supplies', 'description' => 'Clinical diagnostic tools, surgical masks, alcohol, and health essentials.'],
            ['category_name' => 'Non-Medical Supplies', 'description' => 'General health auxiliary materials and clinic utility equipment.'],
            ['category_name' => 'Office Supplies', 'description' => 'Paper, pens, markers, desktop consumables, and clerical supplies.'],
        ])->map(fn (array $category) => $category + ['created_at' => $now, 'updated_at' => $now])->all());

        // Preserve any legacy values that predate the managed lookup lists.
        DB::table('users')->whereNotNull('department')->where('department', '<>', '')
            ->select('department')->distinct()->orderBy('department')->each(function ($user): void {
                DB::table('departments')->insertOrIgnore([
                    'dept_code' => $user->department,
                    'dept_name' => $user->department,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('supplies')->whereNotNull('category')->where('category', '<>', '')
            ->select('category')->distinct()->orderBy('category')->each(function ($supply): void {
                DB::table('supply_categories')->insertOrIgnore([
                    'category_name' => $supply->category,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        DB::table('departments')->orderBy('id')->each(function ($department): void {
            DB::table('users')->where('department', $department->dept_code)
                ->update(['department_id' => $department->id]);
        });
        DB::table('supply_categories')->orderBy('id')->each(function ($category): void {
            DB::table('supplies')->where('category', $category->category_name)
                ->update(['supply_category_id' => $category->id]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('department');
        });
        Schema::table('supplies', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('department')->nullable()->after('school_id');
        });
        Schema::table('supplies', function (Blueprint $table): void {
            $table->string('category')->nullable()->after('model_number');
        });

        DB::table('departments')->orderBy('id')->each(function ($department): void {
            DB::table('users')->where('department_id', $department->id)
                ->update(['department' => $department->dept_code]);
        });
        DB::table('supply_categories')->orderBy('id')->each(function ($category): void {
            DB::table('supplies')->where('supply_category_id', $category->id)
                ->update(['category' => $category->category_name]);
        });
    }
};
