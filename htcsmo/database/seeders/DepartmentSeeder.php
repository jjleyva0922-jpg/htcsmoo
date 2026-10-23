<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $depts = [
            ['dept_code' => 'CETE', 'dept_name' => 'College of Engineering and Technology Education'],
            ['dept_code' => 'CTE',  'dept_name' => 'College of Teacher Education'],
            ['dept_code' => 'CBMA', 'dept_name' => 'College of Business Management and Accountancy'],
            ['dept_code' => 'CCJE', 'dept_name' => 'College of Criminal Justice Education'],
            ['dept_code' => 'CAS',  'dept_name' => 'College of Arts and Sciences'],
        ];

        foreach ($depts as $d) {
            Department::firstOrCreate(['dept_code' => $d['dept_code']], $d);
        }
    }
}
