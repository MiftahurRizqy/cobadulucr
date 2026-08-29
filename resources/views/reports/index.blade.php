@extends('layouts.app')
@section('title','Laporan Leads')
@section('eyebrow','Laporan / Leads')

@section('page-actions')
<div class="relative" x-data="{ periodOpen: false }" @click.outside="periodOpen = false">
    <button type="button" class="btn-secondary h-10 min-w-44 justify-between gap-3 bg-white" @click="periodOpen = !periodOpen">
        <span class="flex items-center gap-2">
            <svg class="size-4 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
            <span class="text-left"><span class="block text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Periode</span><span class="block text-xs font-extrabold text-slate-700">{{ $periodLabel }}</span></span>
        </span>
        <svg class="size-3.5 text-slate-400 transition" :class="periodOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div x-show="periodOpen" x-cloak class="absolute right-0 z-50 mt-2 w-[360px] max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/10">
        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Pilih cepat</div>
        <div class="mt-2 grid grid-cols-3 gap-2">
            <a href="{{ route('reports.index', array_merge(request()->except(['start_date','end_date','report_month','period','date_from','date_to','page']), ['start_date'=>now()->subMonths(3)->addDay()->toDateString(),'end_date'=>now()->toDateString()])) }}" class="rounded-lg bg-slate-50 px-3 py-2 text-center text-[10px] font-extrabold text-slate-600 hover:bg-brand-50 hover:text-brand-700">3 bulan</a>
            <a href="{{ route('reports.index', array_merge(request()->except(['start_date','end_date','report_month','period','date_from','date_to','page']), ['start_date'=>now()->startOfYear()->toDateString(),'end_date'=>now()->toDateString()])) }}" class="rounded-lg bg-slate-50 px-3 py-2 text-center text-[10px] font-extrabold text-slate-600 hover:bg-brand-50 hover:text-brand-700">Tahun ini</a>
            <a href="{{ route('reports.index', array_merge(request()->except(['start_date','end_date','report_month','period','date_from','date_to','page']), ['start_date'=>now()->subYear()->startOfYear()->toDateString(),'end_date'=>now()->subYear()->endOfYear()->toDateString()])) }}" class="rounded-lg bg-slate-50 px-3 py-2 text-center text-[10px] font-extrabold text-slate-600 hover:bg-brand-50 hover:text-brand-700">Tahun lalu</a>
        </div>
        <form method="GET" class="mt-4 border-t border-slate-100 pt-4">
            @foreach(request()->except(['start_date','end_date','report_month','period','date_from','date_to','page']) as $name=>$value) @if(is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif @endforeach
            <div class="grid grid-cols-2 gap-3">
                <label><span class="mb-1.5 block text-[10px] font-bold text-slate-500">Dari tanggal</span><input type="date" name="start_date" value="{{ $dateFrom }}" max="{{ now()->toDateString() }}" class="field h-10 w-full text-xs" required></label>
                <label><span class="mb-1.5 block text-[10px] font-bold text-slate-500">Sampai tanggal</span><input type="date" name="end_date" value="{{ $dateTo }}" max="{{ now()->toDateString() }}" class="field h-10 w-full text-xs" required></label>
            </div>
            <button class="btn-primary mt-3 h-10 w-full">Terapkan periode</button>
        </form>
    </div>
</div>
@endsection

@section('content')
@php
    $conversionView = true;
    $totalOutcome = $wonCount + $lostCount;
    $winRate = $totalOutcome > 0 ? round(($wonCount / $totalOutcome) * 100) : 0;
@endphp

<div class="space-y-5">
    @if(!$conversionView)
        <section>
            <div class="mb-4">
                <h2 class="section-title">Sales Dashboard</h2>
                <p class="mt-1 text-xs text-slate-500">Ringkasan Closed Won {{ $salesFrom->translatedFormat('F Y') }} dan aktivitas tim.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                @foreach([
                    ['Pipeline', 'Rp '.number_format($pipelineValue,0,',','.'), 'bg-indigo-50 text-indigo-700', route('opportunities.index', ['status'=>'open'])],
                    ['Won value', 'Rp '.number_format($wonValue,0,',','.'), 'bg-emerald-50 text-emerald-700', route('opportunities.index', ['status'=>'won'])],
                    ['Won', $wonCount, 'bg-emerald-50 text-emerald-700', route('opportunities.index', ['status'=>'won'])],
                    ['Lost', $lostCount, 'bg-rose-50 text-rose-700', route('opportunities.index', ['status'=>'lost'])],
                    ['Activities', $activities, 'bg-sky-50 text-sky-700', route('activities.index')],
                    ['Overdue task', $overdueTasks, 'bg-amber-50 text-amber-700', route('tasks.index')]
                ] as [$label,$value,$tone,$url])
                    <a href="{{ $url }}" class="card group p-4 transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md">
                        <div class="text-xl font-extrabold text-ink">{{ $value }}</div>
                        <div class="mt-3 flex items-center justify-between rounded-md px-2 py-1 text-[10px] font-extrabold uppercase tracking-wide {{ $tone }}"><span>{{ $label }}</span><span class="transition group-hover:translate-x-0.5">→</span></div>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
            <section class="card relative z-30 overflow-visible">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="section-title">Closed Won by sales</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left">
                        <thead class="table-head"><tr><th class="px-5 py-3">Sales</th><th class="px-4 py-3 text-center">Closed Won</th><th class="px-5 py-3 text-right">Won value</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($byOwner as $ownerSummary)
                                <tr class="transition hover:bg-indigo-50/40"><td class="px-5 py-4 text-sm font-bold text-ink">@if($ownerSummary->owner)<a href="{{ route('opportunities.index', ['owner'=>$ownerSummary->owner_id]) }}" class="hover:text-indigo-700">{{ $ownerSummary->owner->name }} →</a>@else Belum ditentukan @endif</td><td class="px-4 py-4 text-center text-sm text-slate-600">{{ number_format($ownerSummary->total) }}</td><td class="px-5 py-4 text-right text-sm font-extrabold text-ink">Rp {{ number_format((float) $ownerSummary->value,0,',','.') }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="p-12 text-center text-sm text-slate-400">Belum ada data penjualan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card p-5">
                <h3 class="section-title">Conversion snapshot</h3>
                <div class="mx-auto mt-7 grid size-40 place-items-center rounded-full" style="background: conic-gradient(#10b981 0 {{ $winRate }}%, #f1f5f9 {{ $winRate }}% 100%);">
                    <div class="grid size-28 place-items-center rounded-full bg-white text-center">
                        <div><div class="text-3xl font-extrabold text-ink">{{ $winRate }}%</div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Win rate</div></div>
                    </div>
                </div>
                <div class="mt-7 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-emerald-50 p-4 text-center"><div class="text-xl font-extrabold text-emerald-700">{{ $wonCount }}</div><div class="mt-1 text-[10px] font-bold uppercase text-emerald-600">Closed won</div></div>
                    <div class="rounded-xl bg-rose-50 p-4 text-center"><div class="text-xl font-extrabold text-rose-700">{{ $lostCount }}</div><div class="mt-1 text-[10px] font-bold uppercase text-rose-600">Closed lost</div></div>
                </div>
                <a href="{{ route('reports.index', ['view' => 'conversion']) }}" class="btn-secondary mt-5 w-full justify-center">Lihat konversi Customer & Lead</a>
            </section>
        </div>
    @else
        <div x-data="{ exportOpen: false, filterOpen: false }" @keydown.escape.window="filterOpen = false" class="relative flex flex-col gap-5">
            <section class="card relative z-30 overflow-visible">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div><h2 class="section-title">Performa Leads</h2><p class="mt-1 text-xs text-slate-500">Pantau jumlah lead masuk dan perkembangannya menjadi customer.</p></div>
                    <button type="button" @click="exportOpen = true" class="btn-secondary gap-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                        Export laporan
                    </button>
                </div>
                @php
                    $reportFilterCount = collect([
                        request('owner_id'), request('area_id'), request('business_type'),
                        request('source'), request('lead_status'),
                        request('conversion_scope'),
                    ])->filter(fn ($value) => filled($value))->count();
                    $ownerOptions = $owners->pluck('name', 'id')->all();
                    $areaOptions = $areas->pluck('name', 'id')->all();
                    $customerTypeOptions = collect($businessUnits)->mapWithKeys(fn ($unit) => [$unit => $unit])->all();
                    $conversionOptions = [
                        'incoming' => 'Lead masuk',
                        'leads_adds' => 'Leads Adds',
                        'converted' => 'Menjadi customer',
                    ];
                @endphp
                <form method="GET" class="relative p-4">
                    <input type="hidden" name="view" value="conversion">
                    <input type="hidden" name="start_date" value="{{ $dateFrom }}">
                    <input type="hidden" name="end_date" value="{{ $dateTo }}">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="relative min-w-0 flex-1">
                            <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="9" r="6"/><path d="m14 14 4 4"/></svg>
                            <input class="field h-11 pl-10 text-sm" name="search" value="{{ request('search') }}" placeholder="Cari nama lead, perusahaan, PIC, atau ID...">
                        </div>
                        <button type="button" @click="filterOpen = !filterOpen" class="btn-secondary relative h-11 min-w-28 justify-center gap-2">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 5h14M6 10h8M8 15h4"/></svg>
                            Filter
                            @if($reportFilterCount)<span class="grid size-5 place-items-center rounded-full bg-brand-600 text-[10px] font-black text-white">{{ $reportFilterCount }}</span>@endif
                        </button>
                        <button class="btn-primary h-11 min-w-24 justify-center">Cari</button>
                    </div>

                    <div x-show="filterOpen" x-cloak x-transition.origin.top.right @click.outside="filterOpen = false" class="absolute right-4 top-[68px] z-50 w-[min(920px,calc(100%-32px))] rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                        <div class="mb-4 flex items-start justify-between gap-4"><div><h3 class="section-title">Filter laporan konversi</h3><p class="mt-1 text-xs text-slate-500">Pilih filter yang diperlukan saja.</p></div><button type="button" @click="filterOpen=false" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500" aria-label="Tutup"><svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4l12 12M16 4 4 16"/></svg></button></div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div><label class="label">Kategori lead</label><x-scroll-select name="conversion_scope" :options="$conversionOptions" :selected="request('conversion_scope')" placeholder="Semua kategori lead" /></div>
                            <div><label class="label">Sales</label><x-scroll-select name="owner_id" :options="$ownerOptions" :selected="request('owner_id')" placeholder="Semua sales" /></div>
                            <div><label class="label">Area</label><x-scroll-select name="area_id" :options="$areaOptions" :selected="request('area_id')" placeholder="Semua area" /></div>
                            <div><label class="label">Jenis customer</label><x-scroll-select name="business_type" :options="$customerTypeOptions" :selected="request('business_type')" placeholder="Semua jenis customer" /></div>
                            <div><label class="label">Sumber lead</label><x-scroll-select name="source" :options="$sourceOptions" :selected="request('source')" placeholder="Semua sumber" /></div>
                            <div><label class="label">Status lead</label><x-scroll-select name="lead_status" :options="\App\Models\Lead::EDITABLE_STATUSES" :selected="request('lead_status')" placeholder="Semua status" /></div>
                        </div>
                        <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4"><a href="{{ route('reports.index', ['view'=>'conversion']) }}" class="btn-secondary justify-center">Reset</a><button class="btn-primary min-w-32 justify-center">Terapkan</button></div>
                    </div>
                </form>
            </section>

            <div x-show="exportOpen" x-cloak @keydown.escape.window="exportOpen = false" class="fixed inset-0 z-[80] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-sm">
                <form method="GET" @click.outside="exportOpen = false" class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <input type="hidden" name="start_date" value="{{ $dateFrom }}">
                    <input type="hidden" name="end_date" value="{{ $dateTo }}">
                    <header class="flex items-start justify-between border-b border-slate-100 px-5 py-4">
                        <div><h3 class="section-title">Export laporan</h3><p class="mt-1 text-xs text-slate-500">Atur data dan informasi yang ingin dimasukkan ke laporan.</p></div>
                        <button type="button" @click="exportOpen = false" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Tutup"><svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 3l10 10M13 3 3 13"/></svg></button>
                    </header>
                    <div class="scrollbar-thin overflow-y-auto p-5">
                        <div class="mb-5">
                            <div class="mb-3"><h4 class="text-sm font-extrabold text-ink">Filter data export</h4><p class="mt-1 text-xs text-slate-500">File hanya memuat data yang sesuai dengan filter berikut.</p></div>
                            <div class="mb-3">
                                <label class="label">Cari lead</label>
                                <div class="relative"><svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="9" r="6"/><path d="m14 14 4 4"/></svg><input class="field pl-10" name="search" value="{{ request('search') }}" placeholder="Nama lead, perusahaan, PIC, atau ID..."></div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div><label class="label">Kategori lead</label><x-scroll-select name="conversion_scope" :options="$conversionOptions" :selected="request('conversion_scope')" placeholder="Semua kategori lead" /></div>
                                <div><label class="label">Sales</label><x-scroll-select name="owner_id" :options="$ownerOptions" :selected="request('owner_id')" placeholder="Semua sales" /></div>
                                <div><label class="label">Area</label><x-scroll-select name="area_id" :options="$areaOptions" :selected="request('area_id')" placeholder="Semua area" /></div>
                                <div><label class="label">Jenis customer</label><x-scroll-select name="business_type" :options="$customerTypeOptions" :selected="request('business_type')" placeholder="Semua jenis customer" /></div>
                                <div><label class="label">Sumber lead</label><x-scroll-select name="source" :options="$sourceOptions" :selected="request('source')" placeholder="Semua sumber" /></div>
                                <div><label class="label">Status lead</label><x-scroll-select name="lead_status" :options="\App\Models\Lead::EDITABLE_STATUSES" :selected="request('lead_status')" placeholder="Semua status" /></div>
                            </div>
                        </div>
                        <div class="mb-3 border-t border-slate-100 pt-5"><h4 class="text-sm font-extrabold text-ink">Kolom laporan</h4><p class="mt-1 text-xs text-slate-500">Pilih kolom yang ingin ditampilkan.</p></div>
                        <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                            @foreach(['lead_id'=>'ID','company_name'=>'Perusahaan','owner'=>'Sales','source'=>'Sumber lead','status'=>'Status','area'=>'Area','business_unit'=>'Jenis customer','created_at'=>'Tanggal masuk'] as $key=>$label)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50/50">
                                    <input type="checkbox" name="columns[]" value="{{ $key }}" checked class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4">
                        <span class="text-xs text-slate-500">Filter di atas hanya diterapkan pada file yang diunduh.</span>
                        <div class="flex gap-2">
                            <button type="submit" formaction="{{ route('reports.export.csv') }}" class="btn-secondary gap-2 text-emerald-700"><span class="font-extrabold">X</span> Excel</button>
                            <button type="submit" formaction="{{ route('reports.export.pdf') }}" formtarget="_blank" class="btn-primary gap-2"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg> PDF</button>
                        </div>
                    </footer>
                </form>
            </div>

            <section class="relative z-0 order-first grid gap-3 sm:grid-cols-2">
                <div class="card flex items-center gap-4 px-5 py-4">
                    <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16M4 12h16M4 19h16"/></svg>
                    </div>
                    <div class="min-w-0 flex-1"><div class="flex items-baseline justify-between gap-3"><p class="text-sm font-bold text-ink">Lead Masuk</p><strong class="text-2xl leading-none text-ink">{{ number_format($totalLeads) }}</strong></div><p class="mt-1 text-xs text-slate-500">Seluruh lead baru pada periode terpilih</p></div>
                </div>

                <div class="card flex items-center gap-4 px-5 py-4">
                    <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-600">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/><path d="M21 12a9 9 0 1 1-5.3-8.2"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-sm font-bold text-ink">Menjadi customer</p>
                            <strong class="text-2xl leading-none text-ink">{{ number_format($convertedCustomers) }}</strong>
                        </div>
                        <p class="mt-1 text-xs text-slate-500"><span class="font-bold text-sky-600">{{ $conversionRate }}%</span> dari seluruh lead</p>
                    </div>
                </div>

            </section>

            <section id="detail-konversi" class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Detail konversi</h3><p class="mt-1 text-xs text-slate-500">Satu baris mewakili satu lead.</p></div><span class="text-xs font-semibold text-slate-500">{{ $conversionRows->total() }} lead</span></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[1160px] text-left"><thead class="table-head"><tr><th class="px-5 py-3">Lead / perusahaan</th><th class="px-4 py-3">Sumber lead</th><th class="px-4 py-3">Sales</th><th class="px-4 py-3">Area</th><th class="px-4 py-3">Tanggal masuk</th><th class="px-4 py-3">Status konversi</th><th class="px-4 py-3">Produk deal</th><th class="px-5 py-3 text-right">Nilai deal</th></tr></thead><tbody class="divide-y divide-slate-100">
                    @forelse($conversionRows as $lead) @php($customer=$lead->convertedCustomer)
                        <tr class="hover:bg-slate-50/70"><td class="px-5 py-3.5"><div class="text-sm font-bold text-ink">{{ $lead->company_name }}</div><div class="mt-1 text-[10px] text-slate-400">{{ $customer?->customer_id ?? $lead->lead_id }}@if($lead->businessUnit) · {{ $lead->businessUnit->name }}@endif</div></td><td class="px-4 py-3.5"><span class="badge bg-indigo-50 text-indigo-700">{{ $sourceOptions[$lead->source] ?? str($lead->source)->replace('_',' ')->title() }}</span></td><td class="px-4 py-3.5 text-xs font-semibold text-slate-700">{{ $lead->owner?->name ?? '—' }}</td><td class="px-4 py-3.5 text-xs text-slate-600">{{ $lead->area?->name ?? '—' }}</td><td class="px-4 py-3.5 text-xs text-slate-600">{{ $lead->created_at->format('d M Y') }}</td><td class="px-4 py-3.5">@if(!$customer)<span class="badge {{ $lead->status === 'leads_adds' ? 'bg-violet-50 text-violet-700' : 'bg-amber-50 text-amber-700' }}">{{ $lead->statusLabel() }}</span>@elseif(($customer->deal_items_count ?? 0)>0)<span class="badge bg-emerald-50 text-emerald-700">Sudah deal</span>@else<span class="badge bg-sky-50 text-sky-700">Sudah jadi customer</span>@endif</td><td class="px-4 py-3.5 text-xs font-bold text-slate-700">{{ number_format($customer?->deal_items_count ?? 0) }}</td><td class="px-5 py-3.5 text-right text-xs font-extrabold text-ink">Rp {{ number_format((float) ($customer?->deal_value ?? 0),0,',','.') }}</td></tr>
                    @empty <tr><td colspan="8" class="p-14 text-center"><div class="text-sm font-bold text-slate-600">Belum ada data pada filter ini</div><div class="mt-1 text-xs text-slate-400">Ubah periode atau filter untuk melihat data lain.</div></td></tr> @endforelse
                </tbody></table></div>
                @if($conversionRows->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $conversionRows->links() }}</div>@endif
            </section>
        </div>
    @endif
</div>
@endsection
