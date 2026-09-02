<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Pipeline;
use Illuminate\Database\Seeder;

class DeploymentCoreSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BusinessUnitSeeder::class);

        foreach ([
            ['SLS', 'Sales', true, true],
            ['FIN', 'Finance', false, false],
            ['PUR', 'Purchasing', false, false],
            ['WHS', 'Warehouse', false, false],
        ] as [$code, $name, $isFrontliner, $evidenceRequired]) {
            Department::query()->updateOrCreate(
                ['code' => $code],
                [
                    'business_unit_id' => null,
                    'name' => $name,
                    'is_frontliner' => $isFrontliner,
                    'activity_evidence_required' => $evidenceRequired,
                    'is_active' => true,
                ]
            );
        }

    }
}
