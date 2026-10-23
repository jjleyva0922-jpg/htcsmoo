<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    /**
     * Seed the approved accounts used by the requisition workflow.
     */
    public function run(): void
    {
        $password = env('SEED_ACCOUNT_PASSWORD', 'Password123!');

        $accounts = [
            [
                'name' => 'Supply Management Officer',
                'username' => 'smo',
                'school_id' => '90000-0001',
                'email' => 'smo@online.htcgsc.edu.ph',
                'role' => 'smo',
            ],
            [
                'name' => 'School President',
                'username' => 'president',
                'school_id' => '90000-0002',
                'email' => 'president@online.htcgsc.edu.ph',
                'role' => 'president',
            ],
            [
                'name' => 'School Provost',
                'username' => 'provost',
                'school_id' => '90000-0003',
                'email' => 'provost@online.htcgsc.edu.ph',
                'role' => 'provost',
            ],
            [
                'name' => 'VP for Administration',
                'username' => 'vp_admin',
                'school_id' => '90000-0004',
                'email' => 'vp.admin@online.htcgsc.edu.ph',
                'role' => 'vp_admin',
            ],
            [
                'name' => 'VP for Finance',
                'username' => 'vp_finance',
                'school_id' => '90000-0005',
                'email' => 'vp.finance@online.htcgsc.edu.ph',
                'role' => 'vp_finance',
            ],
        ];

        $departmentHeads = [
            'CETE' => ['CETE Department Head', 'head_cete', '91000-0001', 'head.cete@online.htcgsc.edu.ph'],
            'CTE' => ['CTE Department Head', 'head_cte', '91000-0002', 'head.cte@online.htcgsc.edu.ph'],
            'CBMA' => ['CBMA Department Head', 'head_cbma', '91000-0003', 'head.cbma@online.htcgsc.edu.ph'],
            'CCJE' => ['CCJE Department Head', 'head_ccje', '91000-0004', 'head.ccje@online.htcgsc.edu.ph'],
            'CAS' => ['CAS Department Head', 'head_cas', '91000-0005', 'head.cas@online.htcgsc.edu.ph'],
        ];

        foreach ($departmentHeads as $departmentCode => [$name, $username, $schoolId, $email]) {
            $accounts[] = [
                'name' => $name,
                'username' => $username,
                'school_id' => $schoolId,
                'email' => $email,
                'role' => 'dept_head',
                'department' => $departmentCode,
                'department_id' => Department::where('dept_code', $departmentCode)->value('id'),
            ];
        }

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['username' => $account['username']],
                array_merge($account, [
                    'department' => $account['department'] ?? null,
                    'department_id' => $account['department_id'] ?? null,
                    'email_verified_at' => now(),
                    'password' => Hash::make($password),
                    'account_status' => User::ACCOUNT_ACTIVE,
                ]),
            );
        }

        $this->command?->info('Seeded '.count($accounts).' approved workflow accounts.');
        $this->command?->info('Default password: '.$password);
    }
}
