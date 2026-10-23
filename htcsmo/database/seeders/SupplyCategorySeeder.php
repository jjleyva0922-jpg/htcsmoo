<?php

namespace Database\Seeders;

use App\Models\SupplyCategory;
use Illuminate\Database\Seeder;

class SupplyCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['category_name' => 'Cleaning Supplies',      'description' => 'Sanitation, hygiene chemicals, soaps, and janitorial consumables.'],
            ['category_name' => 'Construction Supplies',  'description' => 'Plywood, cement, masonry, lumber, and campus building materials.'],
            ['category_name' => 'Drugs and Medicines',     'description' => 'Clinic pharmaceutical supplies, analgesics, and first-aid medications.'],
            ['category_name' => 'Maintenance Supplies',   'description' => 'Electrical, hardware, lighting, fixtures, and campus upkeep items.'],
            ['category_name' => 'Medical Supplies',       'description' => 'Clinical diagnostic tools, surgical masks, alcohol, and health essentials.'],
            ['category_name' => 'Non-Medical Supplies',   'description' => 'General health auxiliary materials and clinic utility equipment.'],
            ['category_name' => 'Office Supplies',        'description' => 'Paper, pens, markers, desktop consumables, and clerical supplies.'],
        ];

        foreach ($categories as $cat) {
            SupplyCategory::firstOrCreate(['category_name' => $cat['category_name']], $cat);
        }
    }
}
