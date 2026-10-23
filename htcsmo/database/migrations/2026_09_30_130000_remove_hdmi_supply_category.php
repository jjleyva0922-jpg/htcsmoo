<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $officeCategoryId = DB::table('supply_categories')
            ->where('category_name', 'Office Supplies')
            ->value('id');

        DB::table('supplies')
            ->where('category', 'HDMI')
            ->update([
                'category' => 'Office Supplies',
                'supply_category_id' => $officeCategoryId,
            ]);

        DB::table('supply_categories')->where('category_name', 'HDMI')->delete();
    }

    public function down(): void
    {
        DB::table('supply_categories')->insertOrIgnore([
            'category_name' => 'HDMI',
            'description' => 'Audio-visual projection cables, display adapters, and converter cords.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
