@extends('layouts.app')
@section('title','Pipeline Penjualan')
@section('eyebrow','CRM · Kanban')
@section('page-actions')
<div class="hidden gap-2 sm:flex"><a href="{{ route('opportunities.index',['pipeline'=>$pipeline->id]) }}" class="btn-secondary">Tampilan tabel</a><a href="{{ route('opportunities.create') }}" class="btn-primary">+ Opportunity</a></div>
@endsection
@section('content')
@php
    $allOpportunities = $opportunities->flatten();
    $pipelineValue = $allOpportunities->sum(fn($item) => (float) $item->estimated_value);
@endphp
<div class="card mb-4 flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:justify-between">
    <form class="flex items-center gap-2"><label class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pipeline</label><select name="pipeline" class="field min-w-52" onchange="this.form.submit()">@foreach($pipelines as $item)<option value="{{ $item->id }}" @selected($pipeline->id===$item->id)>{{ $item->name }}</option>@endforeach</select></form>
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[10px] text-slate-400"><a href="{{ route('opportunities.custom-progress') }}" class="btn-secondary !h-8 !px-3 text-[10px]">⚙ Custom progress</a><span><b class="text-sm text-ink">{{ $allOpportunities->count() }}</b> opportunity</span><span><b class="text-sm text-ink">Rp {{ number_format($pipelineValue,0,',','.') }}</b> nilai pipeline</span><span class="hidden md:inline">Tarik kartu untuk memindahkan stage, klik untuk melihat detail.</span></div>
</div>

<div data-kanban-shell>
    <div class="kanban-guide mb-3 flex flex-col gap-3 rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-base text-brand-600 shadow-sm">↔</span><div><div data-kanban-hint class="text-xs font-extrabold text-ink">Tarik kartu untuk memindahkan stage</div><div class="mt-0.5 text-[10px] leading-relaxed text-slate-500">Tarik kartu ke kolom tujuan. Aturan wajib pada stage tetap diperiksa.</div></div></div>
        <div class="flex items-center gap-2">
            <span data-kanban-position class="mr-auto text-[10px] font-bold text-slate-500 sm:mr-1">Memuat posisi...</span>
            <button type="button" data-kanban-prev class="btn-secondary !px-3 !py-2 text-[11px] disabled:cursor-not-allowed disabled:opacity-40">← Tahap sebelumnya</button>
            <button type="button" data-kanban-next class="btn-primary !px-3 !py-2 text-[11px] disabled:cursor-not-allowed disabled:opacity-40">Tahap berikutnya →</button>
        </div>
    </div>
    <div class="relative">
        <div data-kanban-left-shadow class="pointer-events-none absolute inset-y-0 left-0 z-20 w-12 bg-gradient-to-r from-slate-100/90 to-transparent opacity-0 transition-opacity"></div>
        <div data-kanban-right-shadow class="pointer-events-none absolute inset-y-0 right-0 z-20 w-16 bg-gradient-to-l from-slate-100/95 to-transparent transition-opacity"></div>
        <div data-kanban-scroll class="scrollbar-kanban flex gap-3 overflow-x-auto pb-5" tabindex="0" aria-label="Tahap pipeline, dapat digeser secara horizontal">
@foreach($pipeline->stages as $stage)
    @php($cards = $opportunities->get($stage->id,collect()))
    <section data-kanban-stage data-stage-id="{{ $stage->id }}" data-stage-is-lost="{{ $stage->is_lost ? '1' : '0' }}" class="kanban-column flex w-[292px] shrink-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-slate-100/60 transition duration-200">
        <div class="shrink-0 border-b border-slate-200/80 bg-white px-3.5 py-3 first:rounded-t-xl">
            <div class="flex items-center justify-between gap-2"><div class="flex min-w-0 items-center gap-2"><span class="size-2.5 shrink-0 rounded-full" style="background:{{ $stage->color }}"></span><h3 class="truncate text-xs font-extrabold text-ink">{{ $stage->name }}</h3><span data-stage-count class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-500">{{ $cards->count() }}</span></div><span class="text-[9px] font-semibold text-slate-400">{{ $stage->probability }}%</span></div>
            <div class="mt-2 flex items-center justify-between text-[9px] text-slate-400"><span>Total nilai</span><b data-stage-total class="text-slate-600">Rp {{ number_format($cards->sum(fn($item)=>(float)$item->estimated_value),0,',','.') }}</b></div>
        </div>
        <div data-kanban-drop class="kanban-stage-list space-y-2.5 overflow-y-auto p-2.5 transition-colors duration-200">
        @forelse($cards as $opp)
            @php($overSla = $opp->days_in_stage > ($stage->sla_days ?? 999))
            <a href="{{ route('opportunities.show',$opp) }}" draggable="true" data-kanban-card data-stage-id="{{ $stage->id }}" data-value="{{ (float) $opp->estimated_value }}" data-move-url="{{ route('opportunities.stage',$opp) }}" class="block cursor-grab rounded-xl border {{ $overSla ? 'border-rose-200' : 'border-slate-200' }} bg-white p-3.5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md active:cursor-grabbing">
                <div class="mb-2 flex items-center justify-between gap-2"><span class="text-[9px] font-bold text-brand-600">{{ $opp->opportunity_id }}</span><span class="badge {{ $opp->priority === 'high' ? 'bg-rose-50 text-rose-600 ring-1 ring-rose-100' : ($opp->priority === 'medium' ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-100' : 'bg-sky-50 text-sky-600 ring-1 ring-sky-100') }}">{{ ['low'=>'Low','medium'=>'Medium','high'=>'High'][$opp->priority] ?? ucfirst($opp->priority) }}</span></div>
                <h4 class="line-clamp-2 text-xs font-extrabold leading-relaxed text-ink">{{ $opp->title }}</h4>
                <p class="mt-1 truncate text-[10px] font-medium text-slate-500">{{ $opp->customer->company_name }}</p>
                @if($opp->items->isNotEmpty() || $opp->product_name)
                    <div class="mt-2 flex min-w-0 items-center gap-1.5 text-[10px]">
                        <span class="min-w-0 truncate text-slate-400">{{ $opp->items->first()?->product_name ?? $opp->product_name }}</span>
                        @if($opp->items->count() > 1)<span class="shrink-0 rounded-full bg-slate-50 px-1.5 py-0.5 font-semibold text-slate-400 ring-1 ring-slate-100">+{{ $opp->items->count() - 1 }} produk lainnya</span>@endif
                    </div>
                @endif
                @if($opp->custom_progress_summary)
                    <p class="mt-2 truncate text-[9px] font-semibold text-violet-700" title="{{ $opp->custom_progress_detail }}">⚙ Pekerjaan Custom: {{ $opp->custom_progress_summary }}</p>
                @endif
                <div class="mt-3 text-sm font-extrabold text-ink">Rp {{ number_format($opp->estimated_value,0,',','.') }}</div>
                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3"><div class="flex min-w-0 items-center gap-2"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-[9px] font-extrabold text-brand-600">{{ mb_substr($opp->owner->name,0,1) }}</span><span class="max-w-24 truncate text-[9px] font-semibold text-slate-500">{{ $opp->owner->name }}</span></div><div class="text-[9px] text-slate-400"><span title="Task terbuka">✓ {{ $opp->tasks->whereNotIn('status',['done','cancelled'])->count() }}</span></div></div>
                <div class="mt-2 flex items-center justify-between text-[9px]"><span data-days-in-stage class="{{ $overSla ? 'font-bold text-rose-500' : 'text-slate-400' }}">{{ $opp->days_in_stage }} hari di tahap ini</span><span class="text-slate-400">{{ $opp->expected_close_date?->format('d M') ?? 'Tanpa target' }}</span></div>
            </a>
        @empty<div class="kanban-empty grid h-28 place-items-center rounded-lg border border-dashed border-slate-300 bg-white/50 px-5 text-center text-[10px] text-slate-400">Belum ada opportunity pada tahap ini</div>@endforelse
        </div>
    </section>
@endforeach
        </div>
    </div>
</div>
<div data-kanban-lost-modal class="fixed inset-0 z-[130] hidden place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
    <form data-kanban-lost-form class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Tandai sebagai Lost</h3><p class="mt-1 text-[10px] text-slate-400">Perpindahan dibatalkan jika modal ini ditutup.</p></div><button type="button" data-kanban-lost-cancel class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Tutup">×</button></div>
        <div class="space-y-4 p-5"><label class="block"><span class="label">Kategori alasan *</span><select data-kanban-lost-reason class="field mt-2" required><option value="">Pilih alasan Lost</option><option value="price">Harga</option><option value="competitor">Kompetitor</option><option value="budget">Anggaran</option><option value="cancelled">Kebutuhan dibatalkan</option><option value="no_response">Tidak ada respons</option><option value="other">Lainnya</option></select></label><label class="block"><span class="label">Detail alasan *</span><textarea data-kanban-lost-detail rows="4" class="field mt-2" placeholder="Jelaskan penyebab opportunity tidak dilanjutkan" required></textarea></label></div>
        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4"><button type="button" data-kanban-lost-cancel class="btn-secondary">Batal</button><button class="btn-primary !bg-rose-600 hover:!bg-rose-700">Tandai sebagai Lost</button></div>
    </form>
</div>
@endsection
