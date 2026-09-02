<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationalSettingController extends Controller
{
    public function index(): View
    {
        return view('settings.operational', [
            'customProgressEnabled' => SystemSetting::bool('custom_progress_enabled', true),
            'marketSegmentEnabled' => SystemSetting::bool('product_market_segment_enabled', true),
            'operationalKpiEnabled' => SystemSetting::bool('operational_kpi_enabled', true),
            'productTypeConfig' => SystemSetting::json('product_type_config', ['enabled' => true, 'regular_label' => 'Reguler', 'custom_label' => 'Custom']),
            'marketSegmentConfig' => SystemSetting::json('market_segment_config', ['drink_label' => 'Drink', 'food_label' => 'Food', 'industry_label' => 'Industri']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['product_type_regular_label' => ['nullable', 'string', 'max:30'], 'product_type_custom_label' => ['nullable', 'string', 'max:30'], 'market_drink_label' => ['nullable', 'string', 'max:30'], 'market_food_label' => ['nullable', 'string', 'max:30'], 'market_industry_label' => ['nullable', 'string', 'max:30']]);
        SystemSetting::setBool('custom_progress_enabled', $request->boolean('custom_progress_enabled'));
        SystemSetting::setBool('product_market_segment_enabled', $request->boolean('product_market_segment_enabled'));
        SystemSetting::setBool('operational_kpi_enabled', $request->boolean('operational_kpi_enabled'));
        SystemSetting::setJson('product_type_config', ['enabled' => $request->boolean('product_type_enabled'), 'regular_label' => trim($data['product_type_regular_label'] ?? '') ?: 'Reguler', 'custom_label' => trim($data['product_type_custom_label'] ?? '') ?: 'Custom']);
        if (! $request->boolean('product_type_enabled')) SystemSetting::setBool('custom_progress_enabled', false);
        SystemSetting::setJson('market_segment_config', ['drink_label' => trim($data['market_drink_label'] ?? '') ?: 'Drink', 'food_label' => trim($data['market_food_label'] ?? '') ?: 'Food', 'industry_label' => trim($data['market_industry_label'] ?? '') ?: 'Industri']);

        return back()->with('success', 'Konfigurasi operasional berhasil disimpan.');
    }
}
