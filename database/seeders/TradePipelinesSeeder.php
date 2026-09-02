<?php

namespace Database\Seeders;

use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TradePipelinesSeeder extends Seeder
{
    public function run(): void
    {
        $createdBy = User::query()->orderBy('id')->value('id');

        if (! $createdBy) {
            throw new \RuntimeException('Pipeline tidak dapat dibuat karena belum ada pengguna perusahaan.');
        }

        $stages = [
            ['Inquiry', 10, '#0ea5e9', false, false],
            ['Sample', 30, '#8b5cf6', false, false],
            ['PO', 50, '#f59e0b', false, false],
            ['Kirim', 70, '#06b6d4', false, false],
            ['Payment', 85, '#f97316', false, false],
            ['Closed Won', 100, '#10b981', true, false],
            ['Closed Lost', 0, '#ef4444', false, true],
        ];

        foreach ([
            ['General Trade', 'general-trade', 'Pipeline penjualan General Trade (GT).'],
            ['Modern Trade', 'modern-trade', 'Pipeline penjualan Modern Trade (MT).'],
            ['Industrial', 'industrial', 'Pipeline penjualan Industrial.'],
        ] as [$name, $slug, $description]) {
            $pipeline = Pipeline::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'is_active' => true, 'created_by' => $createdBy]
            );

            if ($pipeline->stages()->exists()) {
                continue;
            }

            foreach ($stages as $index => [$stageName, $probability, $color, $isWon, $isLost]) {
                $pipeline->stages()->create([
                    'name' => $stageName,
                    'slug' => Str::slug($stageName),
                    'position' => $index + 1,
                    'color' => $color,
                    'probability' => $probability,
                    'sla_days' => in_array($stageName, ['PO', 'Payment'], true) ? 7 : 14,
                    'is_won' => $isWon,
                    'is_lost' => $isLost,
                    'is_active' => true,
                ]);
            }
        }
    }
}
