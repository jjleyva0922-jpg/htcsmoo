<?php

namespace Database\Seeders;

use App\Models\Requisition;
use Illuminate\Database\Seeder;

class BackfillTrackingNumbersSeeder extends Seeder
{
    public function run(): void
    {
        $requisitions = Requisition::with('user')->whereNull('tracking_number')->get();

        foreach ($requisitions as $r) {
            $r->tracking_number = $r->getTrackingCodeFallback();
            $r->saveQuietly();
        }
    }
}
