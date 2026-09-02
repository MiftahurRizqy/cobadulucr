@extends('layouts.app')
@section('title','Opportunity Penjualan')
@section('eyebrow','CRM · Pipeline')
@section('page-actions')
<div class="hidden gap-2 sm:flex"><a href="{{ route('opportunities.kanban',['pipeline'=>request('pipeline')]) }}" class="btn-secondary">Tampilan kanban</a><a href="{{ route('opportunities.create') }}" class="btn-primary"><span class="text-base">+</span> Opportunity</a></div>
@endsection
@section('content')

@php
    $activeFilterCount = collect([
        request('search'),
        request('pipeline'),
        request('stage'),
        request('owner'),
        request('status'),
        request('sort'),
    ])->filter(fn ($value) => filled($value))->count();
@endphp
<form class="card relative z-20 mb-5 overflow-visible p-3" x-data="{
    advancedOpen: false,
    pipeline: @js((string) request('pipeline', '')),
    stage: @js((string) request('stage', '')),
    stages: @js($stages->map(fn ($stage) => ['id' => (string) $stage->id, 'pipeline_id' => (string) $stage->pipeline_id, 'name' => $stage->name])->values()),
    get filteredStages() { return this.pipeline ? this.stages.filter(item => item.pipeline_id === this.pipeline) : []; },
    changePipeline() { if (!this.filteredStages.some(item => item.id === this.stage)) this.stage = ''; }
}" @keydown.escape.window="advancedOpen=false">
    <div class="flex flex-col gap-2 sm:flex-row">
        <div class="relative min-w-0 flex-1">
            <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input class="field h-10 pl-9 text-sm" name="search" value="{{ request('search') }}" placeholder="Cari nama, ID opportunity, atau customer...">
        </div>
        <div class="flex gap-2">
            <button type="button" class="btn-secondary relative h-10 min-w-28" @click="advancedOpen=!advancedOpen">
                <svg class="mr-1.5 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                Filter
                @if($activeFilterCount)<span class="ml-1.5 grid size-5 place-items-center rounded-full bg-brand-600 text-[9px] font-black text-white">{{ $activeFilterCount }}</span>@endif
            </button>
            <button class="btn-primary h-10 min-w-20">Cari</button>
        </div>
    </div>

    <div x-show="advancedOpen" x-cloak x-transition.origin.top.right @click.outside="advancedOpen=false" class="absolute right-3 top-[58px] z-50 w-[min(760px,calc(100%-24px))] rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div><h3 class="text-sm font-extrabold text-ink">Filter opportunity</h3><p class="mt-0.5 text-[10px] text-slate-400">Pilih filter yang diperlukan saja.</p></div>
            <button type="button" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200" @click="advancedOpen=false" aria-label="Tutup filter"><svg class="block size-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3.5 3.5l9 9M12.5 3.5l-9 9"/></svg></button>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div><label class="mb-1.5 block text-[11px] font-bold text-slate-600">Pipeline</label><select class="field text-sm" name="pipeline" x-model="pipeline" @change="changePipeline()"><option value="">Semua pipeline</option>@foreach($pipelines as $pipeline)<option value="{{ $pipeline->id }}">{{ $pipeline->name }}</option>@endforeach</select></div>
            <div><label class="mb-1.5 block text-[11px] font-bold text-slate-600">Tahap</label><select class="field text-sm disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400" name="stage" x-model="stage" :disabled="!pipeline"><option value="" x-text="pipeline ? 'Semua tahap' : 'Pilih pipeline terlebih dahulu'"></option><template x-for="item in filteredStages" :key="item.id"><option :value="item.id" x-text="item.name"></option></template></select></div>
            <div><label class="mb-1.5 block text-[11px] font-bold text-slate-600">Sales</label><select class="field text-sm" name="owner"><option value="">Semua sales</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected(request('owner')==$owner->id)>{{ $owner->name }}</option>@endforeach</select></div>
            <div><label class="mb-1.5 block text-[11px] font-bold text-slate-600">Status</label><select class="field text-sm" name="status"><option value="">Semua status</option>@foreach(['open'=>'Open','won'=>'Won','lost'=>'Lost'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="mb-1.5 block text-[11px] font-bold text-slate-600">Urutan</label><select class="field text-sm" name="sort"><option value="">Terbaru</option><option value="value" @selected(request('sort')==='value')>Nilai terbesar</option><option value="close" @selected(request('sort')==='close')>Closing terdekat</option></select></div>
        </div>
        <div class="mt-4 flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('opportunities.index') }}" class="btn-secondary min-w-28">Reset</a>
            <button class="btn-primary min-w-28">Terapkan</button>
        </div>
    </div>
</form>

<section class="card overflow-hidden">
    <header class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3"><div class="flex items-center gap-2 text-xs"><b class="text-ink">{{ $opportunities->total() }} opportunity</b><span class="text-slate-400">·</span><span class="text-slate-500">Data {{ $opportunities->firstItem()??0 }}–{{ $opportunities->lastItem()??0 }}</span></div><div class="flex gap-2 sm:hidden"><a href="{{ route('opportunities.kanban',['pipeline'=>request('pipeline')]) }}" class="filter-chip">Kanban</a><a href="{{ route('opportunities.create') }}" class="btn-primary !px-3 !py-2">+ Baru</a></div></header>
    <div class="scrollbar-kanban overflow-x-auto">
        <table class="w-full min-w-[1110px] table-fixed border-collapse text-left">
            <thead class="bg-slate-100"><tr class="border-b border-slate-300 text-[10px] font-extrabold uppercase tracking-wide text-slate-500">
                <th class="w-[220px] border-r border-slate-200 px-3 py-3">Opportunity</th>
                <th class="w-[170px] border-r border-slate-200 px-3 py-3">Customer</th>
                <th class="w-[130px] border-r border-slate-200 px-3 py-3">Tahap</th>
                <th class="w-[145px] border-r border-slate-200 px-3 py-3">Sales</th>
                <th class="w-[130px] border-r border-slate-200 px-3 py-3 text-right">Nilai</th>
                <th class="w-[105px] border-r border-slate-200 px-3 py-3 text-center">Peluang</th>
                <th class="w-[120px] border-r border-slate-200 px-3 py-3">Target closing</th>
                <th class="w-[90px] border-r border-slate-200 px-3 py-3">Status</th>
                <th class="w-[76px] px-3 py-3 text-center">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-200">
            @forelse($opportunities as $opportunity)
                @php
                    $overSla = $opportunity->days_in_stage > ($opportunity->stage->sla_days ?? 999);
                    $primaryProduct = $opportunity->items->first()?->product_name ?: $opportunity->product_name;
                    $additionalProductCount = max(0, $opportunity->items->count() - 1);
                @endphp
                <tr class="transition hover:bg-indigo-50/40">
                    <td class="border-r border-slate-100 px-3 py-3"><a href="{{ route('opportunities.show',$opportunity) }}" class="block truncate text-[12px] font-bold text-ink hover:text-brand-600" title="{{ $opportunity->title }}">{{ $opportunity->title }}</a><div class="mt-1 flex min-w-0 items-center gap-1 text-[9px] text-slate-400"><span class="shrink-0 font-semibold text-brand-600">{{ $opportunity->opportunity_id }}</span>@if($primaryProduct)<span>·</span><span class="truncate">{{ $primaryProduct }}</span>@endif @if($additionalProductCount > 0)<span class="shrink-0 rounded-full bg-slate-50 px-1.5 py-0.5 font-semibold text-slate-400 ring-1 ring-slate-100">+{{ $additionalProductCount }} produk lainnya</span>@endif</div></td>
                    <td class="border-r border-slate-100 px-3 py-3"><div class="truncate text-[11px] font-semibold text-slate-700" title="{{ $opportunity->customer->company_name }}">{{ $opportunity->customer->company_name }}</div></td>
                    <td class="border-r border-slate-100 px-3 py-3"><span class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-2 py-1 text-[10px] font-bold" style="color:{{ $opportunity->stage->color }};border-color:{{ $opportunity->stage->color }}35;background:{{ $opportunity->stage->color }}0D"><span class="size-1.5 shrink-0 rounded-full" style="background:{{ $opportunity->stage->color }}"></span><span class="truncate">{{ $opportunity->stage->name }}</span></span>@if($overSla)<div class="mt-1 text-[9px] font-bold text-rose-500">Melewati SLA</div>@endif</td>
                    <td class="border-r border-slate-100 px-3 py-3"><div class="flex min-w-0 items-center gap-2"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-slate-100 text-[8px] font-bold text-slate-500">{{ mb_substr($opportunity->owner->name,0,1) }}</span><span class="truncate text-[11px] font-semibold text-slate-700">{{ $opportunity->owner->name }}</span></div></td>
                    <td class="border-r border-slate-100 px-3 py-3 text-right text-[11px] font-extrabold text-ink">Rp {{ number_format($opportunity->estimated_value,0,',','.') }}</td>
                    <td class="border-r border-slate-100 px-3 py-3"><div class="mx-auto w-16"><div class="mb-1 text-center text-[10px] font-bold text-slate-500">{{ $opportunity->probability }}%</div><div class="h-1 overflow-hidden rounded bg-slate-100"><div class="h-full rounded bg-brand-500" style="width:{{ $opportunity->probability }}%"></div></div></div></td>
                    <td class="border-r border-slate-100 px-3 py-3 text-[10px] font-medium {{ $opportunity->expected_close_date?->isPast()&&$opportunity->status==='open'?'text-rose-600':'text-slate-500' }}">{{ $opportunity->expected_close_date?->translatedFormat('d M Y')??'Belum diatur' }}</td>
                    <td class="border-r border-slate-100 px-3 py-3"><span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-bold {{ $opportunity->status==='won'?'bg-emerald-50 text-emerald-600':($opportunity->status==='lost'?'bg-rose-50 text-rose-600':'bg-sky-50 text-sky-600') }}"><span class="size-1.5 rounded-full bg-current"></span>{{ ['open'=>'Open','won'=>'Won','lost'=>'Lost'][$opportunity->status]??ucfirst($opportunity->status) }}</span></td>
                    <td class="px-3 py-3 text-center"><a href="{{ route('opportunities.show',$opportunity) }}" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-slate-600 shadow-sm hover:text-brand-700">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty-state min-h-[150px]"><div class="font-bold text-slate-600">Opportunity tidak ditemukan</div><div class="mt-1 text-xs">Ubah filter atau buat opportunity baru.</div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
<div class="mt-4">{{ $opportunities->links() }}</div>
@endsection
