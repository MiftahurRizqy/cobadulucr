<?php

namespace App\Services;

use App\Models\BusinessUnit;
use App\Models\KpiMetric;
use App\Models\Pipeline;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantConfigurationCloner
{
    /** Capture configuration from the active source tenant before switching DB connections. */
    public function export(): array
    {
        $roleSlugs = Role::query()->pluck('slug', 'id');

        return [
            'business_units' => BusinessUnit::query()->orderBy('id')->get()
                ->map(fn (BusinessUnit $unit) => $unit->only(['code', 'name', 'is_active']))->all(),
            'settings' => SystemSetting::query()->orderBy('id')->get()
                ->map(fn (SystemSetting $setting) => [
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'role_slug' => $setting->role_id ? $roleSlugs->get($setting->role_id) : null,
                ])->all(),
            'metrics' => KpiMetric::query()->orderBy('sort_order')->get()
                ->map(fn (KpiMetric $metric) => $metric->only([
                    'name', 'source', 'filters', 'unit', 'threshold', 'is_active',
                    'counts_in_achievement', 'sort_order', 'legacy_key',
                ]))->all(),
            'pipelines' => Pipeline::query()->with(['businessUnit', 'stages.rules'])->orderBy('id')->get()
                ->map(function (Pipeline $pipeline) {
                    return [
                        'attributes' => $pipeline->only([
                            'name', 'slug', 'description', 'business_type', 'counts_as_custom_noo',
                            'uses_pipeline_for_custom_progress', 'is_active',
                        ]),
                        'business_unit_code' => $pipeline->businessUnit?->code,
                        'stages' => $pipeline->stages->map(function ($stage) {
                            return [
                                'attributes' => $stage->only([
                                    'name', 'slug', 'position', 'color', 'probability', 'sla_days',
                                    'is_won', 'is_lost', 'is_active',
                                ]),
                                'rules' => $stage->rules->map(fn ($rule) => $rule->only([
                                    'rule_type', 'field_key', 'label', 'configuration', 'is_mandatory',
                                ]))->all(),
                            ];
                        })->all(),
                    ];
                })->all(),
        ];
    }

    /** Import into the newly migrated tenant; business data and users are deliberately excluded. */
    public function import(array $configuration): void
    {
        DB::transaction(function () use ($configuration) {
            $businessUnitIds = collect($configuration['business_units'] ?? [])->mapWithKeys(function (array $unit) {
                $model = BusinessUnit::query()->updateOrCreate(
                    ['code' => $unit['code']],
                    ['name' => $unit['name'], 'is_active' => $unit['is_active']]
                );

                return [$unit['code'] => $model->id];
            });

            foreach ($configuration['settings'] ?? [] as $setting) {
                $roleId = $setting['role_slug']
                    ? Role::query()->where('slug', $setting['role_slug'])->value('id')
                    : null;
                if ($setting['role_slug'] && ! $roleId) continue;

                SystemSetting::query()->updateOrCreate(
                    ['key' => $setting['key'], 'role_id' => $roleId],
                    ['value' => $setting['value']]
                );
            }

            KpiMetric::query()->delete();
            foreach ($configuration['metrics'] ?? [] as $metric) {
                KpiMetric::query()->create($metric);
            }

            $adminId = User::query()->where('authority_level', 'master_admin')->value('id');
            foreach ($configuration['pipelines'] ?? [] as $pipelineData) {
                $attributes = $pipelineData['attributes'];
                $attributes['business_unit_id'] = $pipelineData['business_unit_code']
                    ? $businessUnitIds->get($pipelineData['business_unit_code'])
                    : null;
                $attributes['created_by'] = $adminId;

                $pipeline = Pipeline::query()->create($attributes);
                foreach ($pipelineData['stages'] as $stageData) {
                    $stage = $pipeline->stages()->create($stageData['attributes']);
                    foreach ($stageData['rules'] as $rule) {
                        $stage->rules()->create($rule);
                    }
                }
            }
        });
    }
}
