<?php

namespace App\Http\Controllers;

use App\Models\KpiMetric;
use App\Models\Pipeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KpiMetricSettingController extends Controller
{
    public function index(): View
    {
        return view('settings.kpi-metrics', ['metrics' => KpiMetric::orderBy('sort_order')->get(), 'pipelines' => Pipeline::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'metrics' => ['required', 'array'],
            'metrics.*.label' => ['required', 'string', 'max:40'],
            'metrics.*.source' => ['required', 'in:'.implode(',', array_keys(KpiMetric::SOURCES))],
            'metrics.*.enabled' => ['nullable', 'boolean'], 'metrics.*.achievement' => ['nullable', 'boolean'],
            'metrics.*.threshold' => ['nullable', 'integer', 'min:0'], 'metrics.*.pipeline_id' => ['nullable', 'integer', 'exists:pipelines,id'],
            'metrics.*.product_type' => ['nullable', 'in:regular,custom'], 'metrics.*.market_segment' => ['nullable', 'in:drink,food,industry'],
        ]);
        $keptIds = collect($data['metrics'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
        // Delete each model so its deletion is retained in the audit trail.
        KpiMetric::query()
            ->when($keptIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $keptIds), fn ($query) => $query)
            ->get()
            ->each
            ->delete();
        foreach ($data['metrics'] as $index => $metric) {
            $model = ! empty($metric['id']) ? KpiMetric::findOrFail($metric['id']) : new KpiMetric();
            $model->fill(['name'=>$metric['label'], 'source'=>$metric['source'], 'filters'=>array_filter(['pipeline_id'=>$metric['pipeline_id'] ?? null, 'product_type'=>$metric['product_type'] ?? null, 'market_segment'=>$metric['market_segment'] ?? null]), 'unit'=>$metric['source'] === 'won_revenue' ? 'currency' : ($metric['source'] === 'won_quantity' ? 'pcs' : 'count'), 'threshold'=>$metric['source'] === 'large_account' ? ($metric['threshold'] ?? 0) : null, 'is_active'=>(bool)($metric['enabled'] ?? false), 'counts_in_achievement'=>(bool)($metric['achievement'] ?? false), 'sort_order'=>$index+1])->save();
        }

        return back()->with('success', 'KPI Metrics berhasil disimpan.');
    }
}
