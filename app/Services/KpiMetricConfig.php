<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\KpiMetric;

class KpiMetricConfig
{
    public const DEFAULTS = [
        'noo' => ['label' => 'Total NOO', 'enabled' => true],
        'custom_noo' => ['label' => 'NOO Custom', 'enabled' => true],
        'large_account' => ['label' => 'Akun Besar', 'enabled' => true, 'threshold' => 50_000_000],
        'drink' => ['label' => 'Drink', 'enabled' => true],
        'food' => ['label' => 'Food', 'enabled' => true],
    ];

    public function all(): array
    {
        $productTypesEnabled = SystemSetting::json('product_type_config', ['enabled' => true])['enabled'] ?? true;
        if (class_exists(KpiMetric::class) && KpiMetric::query()->exists()) {
            return KpiMetric::query()->whereNotNull('legacy_key')->orderBy('sort_order')->get()
                ->mapWithKeys(fn (KpiMetric $metric) => [$metric->legacy_key => [
                    'label' => $metric->name, 'enabled' => $metric->is_active && ($productTypesEnabled || empty(($metric->filters ?? [])['product_type'])),
                    'threshold' => $metric->threshold ?? 0, 'source' => $metric->source,
                    'filters' => $metric->filters ?? [], 'achievement' => $metric->counts_in_achievement,
                ]])->all() + self::DEFAULTS;
        }

        $saved = SystemSetting::json('kpi_metrics');

        return collect(self::DEFAULTS)->map(function (array $defaults, string $key) use ($saved) {
            $value = is_array($saved[$key] ?? null) ? $saved[$key] : [];

            return [
                'label' => trim((string) ($value['label'] ?? $defaults['label'])) ?: $defaults['label'],
                'enabled' => array_key_exists('enabled', $value) ? (bool) $value['enabled'] : $defaults['enabled'],
                'threshold' => (int) ($value['threshold'] ?? ($defaults['threshold'] ?? 0)),
            ];
        })->all();
    }

    public function save(array $metrics): void
    {
        SystemSetting::setJson('kpi_metrics', $metrics);
    }
}
