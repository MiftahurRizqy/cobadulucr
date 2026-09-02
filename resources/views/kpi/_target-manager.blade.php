@if($canManageTemplates)
<div x-data="{ open: @js($errors->any()) }" @open-kpi-targets.window="open = true" x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-[160] grid place-items-center bg-slate-950/55 p-3 backdrop-blur-sm sm:p-6">
    <div class="flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="open = false">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
            <div><h2 class="text-lg font-black text-ink">Atur Target KPI</h2><p class="mt-1 text-xs text-slate-400">Template role dan target massal · {{ $periodLabel }}</p></div>
            <button type="button" class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" @click="open = false" aria-label="Tutup">×</button>
        </header>
        <div class="overflow-y-auto bg-slate-50/70 p-4 sm:p-6">
            @if(!$isSingleMonth)
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">Pilih satu bulan terlebih dahulu untuk menerapkan target massal.</div>
            @endif
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach(['sales' => 'Sales', 'telesales' => 'Telesales'] as $roleSlug => $roleLabel)
                    @php
                        $template = $templates->get($roleSlug);
                    @endphp
                    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="mb-4 flex items-start justify-between gap-3"><div><h3 class="text-sm font-black text-ink">Template {{ $roleLabel }}</h3><p class="mt-1 text-[10px] text-slate-400">Default otomatis untuk {{ $roleLabel }} baru.</p></div>@if($template)<span class="badge bg-emerald-50 text-emerald-700">Tersimpan</span>@endif</div>
                        <form method="POST" action="{{ route('kpi.templates.update', $roleSlug) }}" class="space-y-3">
                            @csrf @method('PUT')
                            <label class="block"><span class="mb-1 block text-[10px] font-bold text-slate-500">Target penjualan</span><div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Rp</span><input class="field !pl-9" type="text" inputmode="numeric" data-money name="sales_target" value="{{ number_format((float)($template?->sales_target ?? 0),0,',','.') }}" required></div></label>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                                @if($kpiMetrics['noo']['enabled'])<label><span class="mb-1 block text-[10px] font-bold text-slate-500">{{ $kpiMetrics['noo']['label'] }}</span><input class="field" type="number" min="0" name="noo_target" value="{{ (int)($template?->noo_target ?? 0) }}" required></label>@endif
                                @if($kpiMetrics['custom_noo']['enabled'])<label><span class="mb-1 block text-[10px] font-bold text-slate-500">{{ $kpiMetrics['custom_noo']['label'] }}</span><input class="field" type="number" min="0" name="custom_noo_target" value="{{ (int)($template?->custom_noo_target ?? 0) }}" required></label>@endif
                                @if($kpiMetrics['large_account']['enabled'])<label><span class="mb-1 block text-[10px] font-bold text-slate-500">{{ $kpiMetrics['large_account']['label'] }}</span><input class="field" type="number" min="0" name="large_account_target" value="{{ (int)($template?->large_account_target ?? 6) }}" required></label>@endif
                                @if($kpiMetrics['drink']['enabled'])<label><span class="mb-1 block text-[10px] font-bold text-slate-500">{{ $kpiMetrics['drink']['label'] }}</span><input class="field" type="number" min="0" name="drink_volume_target" value="{{ (int)($template?->drink_volume_target ?? 0) }}" required></label>@endif
                                @if($kpiMetrics['food']['enabled'])<label><span class="mb-1 block text-[10px] font-bold text-slate-500">{{ $kpiMetrics['food']['label'] }}</span><input class="field" type="number" min="0" name="food_volume_target" value="{{ (int)($template?->food_volume_target ?? 0) }}" required></label>@endif
                            </div>
                            <button class="btn-secondary h-9 w-full">Simpan template</button>
                        </form>
                        <form method="POST" action="{{ route('kpi.templates.apply', $roleSlug) }}" class="mt-3 border-t border-slate-100 pt-3">
                            @csrf
                            <input type="hidden" name="period" value="{{ $from->format('Y-m') }}">
                            <div class="flex items-center justify-between gap-3"><label class="flex items-center gap-2 text-[10px] font-semibold text-slate-500"><input type="checkbox" name="preserve_existing" value="1" class="size-4 accent-brand-600">Pertahankan target yang diatur manual</label><button class="btn-primary h-9" {{ !$template || !$isSingleMonth ? 'disabled' : '' }}>Terapkan ke {{ $roleLabel }}</button></div>
                        </form>
                    </section>
                @endforeach
            </div>
            <form method="POST" action="{{ route('kpi.targets.copy-previous') }}" class="mt-4 flex flex-col gap-3 rounded-2xl border border-indigo-100 bg-indigo-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                @csrf
                <input type="hidden" name="period" value="{{ $from->format('Y-m') }}">
                <div><div class="text-xs font-black text-indigo-900">Salin target bulan sebelumnya</div><div class="mt-1 text-[10px] text-indigo-600">{{ $from->subMonth()->locale('id')->translatedFormat('F Y') }} → {{ $from->locale('id')->translatedFormat('F Y') }}, mengikuti target masing-masing sales.</div></div>
                <div class="flex shrink-0 items-center gap-3"><label class="flex items-center gap-2 text-[10px] font-semibold text-indigo-700"><input type="checkbox" name="overwrite" value="1" class="size-4 accent-brand-600">Timpa yang ada</label><button class="btn-secondary h-9 bg-white" {{ !$isSingleMonth ? 'disabled' : '' }}>Salin target</button></div>
            </form>
        </div>
    </div>
</div>
@endif
