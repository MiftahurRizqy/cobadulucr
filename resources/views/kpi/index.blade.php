@extends('layouts.app')

@section('title', 'KPI Penjualan')
@section('eyebrow', 'Monitoring Kinerja')

@section('page-actions')
<div class="flex items-center justify-end gap-2" x-data="{ exportOpen: false, periodOpen: false }">
    <div class="relative" @click.outside="periodOpen = false">
        <button type="button" class="btn-secondary h-10 min-w-44 justify-between gap-3 bg-white" @click="periodOpen = !periodOpen; exportOpen = false">
            <span class="flex items-center gap-2">
                <svg class="size-4 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
                <span class="text-left"><span class="block text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Periode</span><span class="block text-xs font-extrabold text-slate-700">{{ $periodLabel }}</span></span>
            </span>
            <svg class="size-3.5 text-slate-400 transition" :class="periodOpen && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div x-show="periodOpen" x-cloak class="absolute right-0 z-50 mt-2 w-[360px] max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/10">
            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Pilih cepat</div>
            <div class="mt-2 grid grid-cols-3 gap-2">
                <a href="{{ route('kpi.index', ['start_date' => now()->subMonths(3)->addDay()->toDateString(), 'end_date' => now()->toDateString()]) }}" class="rounded-lg bg-slate-50 px-3 py-2 text-center text-[10px] font-extrabold text-slate-600 hover:bg-brand-50 hover:text-brand-700">3 bulan</a>
                <a href="{{ route('kpi.index', ['start_date' => now()->startOfYear()->toDateString(), 'end_date' => now()->toDateString()]) }}" class="rounded-lg bg-slate-50 px-3 py-2 text-center text-[10px] font-extrabold text-slate-600 hover:bg-brand-50 hover:text-brand-700">Tahun ini</a>
                <a href="{{ route('kpi.index', ['start_date' => now()->subYear()->startOfYear()->toDateString(), 'end_date' => now()->subYear()->endOfYear()->toDateString()]) }}" class="rounded-lg bg-slate-50 px-3 py-2 text-center text-[10px] font-extrabold text-slate-600 hover:bg-brand-50 hover:text-brand-700">Tahun lalu</a>
            </div>
            <form method="GET" class="mt-4 border-t border-slate-100 pt-4">
                <div class="grid grid-cols-2 gap-3">
                    <label><span class="mb-1.5 block text-[10px] font-bold text-slate-500">Dari tanggal</span><input type="date" name="start_date" value="{{ $from->toDateString() }}" max="{{ now()->toDateString() }}" class="field h-10 w-full text-xs" required></label>
                    <label><span class="mb-1.5 block text-[10px] font-bold text-slate-500">Sampai tanggal</span><input type="date" name="end_date" value="{{ $to->toDateString() }}" max="{{ now()->toDateString() }}" class="field h-10 w-full text-xs" required></label>
                </div>
                <button class="btn-primary mt-3 h-10 w-full">Terapkan periode</button>
            </form>
        </div>
    </div>
    <div class="relative" @click.outside="exportOpen = false">
        <button type="button" class="btn-secondary h-10 gap-2 bg-white" @click="exportOpen = !exportOpen; periodOpen = false">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v12m0 0 5-5m-5 5-5-5M5 21h14"/></svg>
            Download laporan
        </button>
        <div x-show="exportOpen" x-cloak class="absolute right-0 z-50 mt-2 w-44 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl">
            <a href="{{ route('kpi.export.excel', ['start_date' => $from->toDateString(), 'end_date' => $to->toDateString()]) }}" class="block rounded-lg px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Excel (.xlsx)</a>
            <a href="{{ route('kpi.export.pdf', ['start_date' => $from->toDateString(), 'end_date' => $to->toDateString()]) }}" class="block rounded-lg px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">PDF (.pdf)</a>
        </div>
    </div>
</div>
@endsection

@section('content')
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="metric"><div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Target tim</div><div class="mt-2 text-xl font-black text-ink">Rp {{ number_format($summary->target, 0, ',', '.') }}</div></div>
    <div class="metric"><div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Realisasi deal</div><div class="mt-2 text-xl font-black text-emerald-600">Rp {{ number_format($summary->realization, 0, ',', '.') }}</div></div>
    <div class="metric"><div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Pencapaian</div><div class="mt-2 text-xl font-black text-brand-600">{{ number_format($summary->achievement, 1, ',', '.') }}%</div></div>
    <div class="metric"><div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Opportunity Closed Won</div><div class="mt-2 text-xl font-black text-ink">{{ number_format($summary->deals) }}</div></div>
</div>

<div class="card mt-5 overflow-hidden" x-data="{ selected: null, review: null }">
    <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div><h2 class="section-title">Kinerja Tim · {{ $periodLabel }}</h2><p class="mt-1 text-[11px] text-slate-400">Target menjumlahkan target bulanan pada bulan yang tercakup. Realisasi mengikuti tanggal Closed Won yang dipilih.</p></div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left">
            <thead class="table-head"><tr><th class="px-5 py-3">Head / Sales</th><th class="px-4 py-3">Target</th><th class="px-4 py-3">Realisasi</th><th class="px-4 py-3">Closing</th><th class="px-4 py-3">Closing rate</th><th class="px-4 py-3">Pencapaian</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($displayRows as $displayRow)
                @if($displayRow->type === 'head')
                    @php $headRow = $displayRow->data; @endphp
                    <tr class="border-y border-indigo-100 bg-indigo-50/70">
                        <td class="px-5 py-4"><div class="text-sm font-black text-ink">{{ $headRow->head->name }}</div><div class="mt-0.5 text-[9px] font-extrabold uppercase tracking-wider text-brand-600">Head · {{ $headRow->salesCount }} sales</div></td>
                        <td class="px-4 py-4 text-xs font-extrabold text-slate-700">Rp {{ number_format($headRow->target,0,',','.') }}</td><td class="px-4 py-4 text-xs font-black text-emerald-700">Rp {{ number_format($headRow->realization,0,',','.') }}</td><td class="px-4 py-4 text-xs font-extrabold text-slate-700">{{ $headRow->deals }} opportunity</td><td class="px-4 py-4 text-xs font-extrabold text-slate-700">{{ number_format($headRow->closingRate,1,',','.') }}%</td><td class="px-4 py-4"><div class="text-xs font-black text-brand-700">{{ number_format($headRow->achievement,1,',','.') }}%</div><div class="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-white"><div class="h-full rounded-full bg-brand-600" style="width:{{ min(100,$headRow->achievement) }}%"></div></div></td><td></td>
                    </tr>
                @else
                @php $row = $displayRow->data; @endphp
                @php
                    $editPayload = ['id' => $row->sales->id, 'name' => $row->sales->name, 'target' => number_format((float) ($row->target?->sales_target ?? 0), 0, ',', '.'), 'nooTarget' => (int)($row->target?->noo_target ?? 0), 'customNooTarget' => (int)($row->target?->custom_noo_target ?? 0), 'drinkVolumeTarget' => (int)($row->target?->drink_volume_target ?? 0), 'foodVolumeTarget' => (int)($row->target?->food_volume_target ?? 0), 'realization' => $row->realization, 'notes' => $row->target?->evaluation_notes ?? ''];
                    $reviewPayload = ['name' => $row->sales->name, 'target' => 'Rp '.number_format((float) ($row->target?->sales_target ?? 0), 0, ',', '.'), 'realization' => 'Rp '.number_format($row->realization, 0, ',', '.'), 'achievement' => number_format($row->achievement, 1, ',', '.').'%', 'noo' => number_format($row->noo,0,',','.').' / '.number_format((int)($row->target?->noo_target ?? 0),0,',','.'), 'customNoo' => number_format($row->customNoo,0,',','.').' / '.number_format((int)($row->target?->custom_noo_target ?? 0),0,',','.'), 'drinkVolume' => number_format($row->drinkVolume,0,',','.').' / '.number_format((int)($row->target?->drink_volume_target ?? 0),0,',','.').' pcs', 'foodVolume' => number_format($row->foodVolume,0,',','.').' / '.number_format((int)($row->target?->food_volume_target ?? 0),0,',','.').' pcs', 'notes' => $row->target?->evaluation_notes ?: 'Belum ada catatan dari Head/Manager.', 'hasComment' => filled($row->target?->evaluation_notes), 'commenter' => $row->target?->updater?->name, 'commentedAt' => $row->target?->updated_at?->translatedFormat('d M Y · H:i').' WIB'];
                @endphp
                <tr class="hover:bg-slate-50/70">
                    <td class="px-5 py-4"><div class="flex items-center gap-3 @if($row->sales->manager) pl-5 @endif"><div class="h-8 w-0.5 rounded-full bg-slate-200"></div><div><div class="text-sm font-extrabold text-ink">{{ $row->sales->name }}</div><div class="mt-0.5 text-[9px] font-bold uppercase tracking-wide text-slate-400">Sales</div></div></div></td>
                    <td class="px-4 py-4 text-xs font-bold text-slate-600">Rp {{ number_format((float) ($row->target?->sales_target ?? 0), 0, ',', '.') }}</td>
                    <td class="px-4 py-4 text-xs font-extrabold text-emerald-600">Rp {{ number_format($row->realization, 0, ',', '.') }}</td>
                    <td class="px-4 py-4 text-xs font-bold text-slate-600">{{ $row->deals }} opportunity</td>
                    <td class="px-4 py-4 text-xs font-bold text-slate-600">{{ number_format($row->closingRate, 1, ',', '.') }}%</td>
                    <td class="px-4 py-4"><div class="text-xs font-extrabold text-brand-600">{{ number_format($row->achievement, 1, ',', '.') }}%</div><div class="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ min(100, $row->achievement) }}%"></div></div></td>
                    <td class="px-5 py-4 text-right"><div class="flex justify-end gap-2">
                        <button type="button" class="btn-secondary h-8" @click="review = {{ Illuminate\Support\Js::from($reviewPayload) }}">Lihat</button>
                        @if($row->canManage && $isSingleMonth)
                        <button type="button" class="btn-secondary h-8" @click="selected = {{ Illuminate\Support\Js::from($editPayload) }}">Atur</button>
                        @endif
                    </div></td>
                </tr>
                @endif
            @empty
                <tr><td colspan="7" class="px-6 py-14 text-center text-sm text-slate-400">Belum ada sales dalam cakupan tim Anda.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div x-show="review" x-cloak class="fixed inset-0 z-[150] grid place-items-center bg-slate-950/45 p-4" @keydown.escape.window="review = null">
        <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="review = null">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                <div><h3 class="text-lg font-black text-ink">Evaluasi <span x-text="review?.name"></span></h3><p class="mt-1 text-xs text-slate-400">Periode {{ $periodLabel }}</p></div>
                <button type="button" class="grid size-8 place-items-center rounded-full bg-slate-100 text-slate-500" @click="review = null">×</button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-3">
                    <div class="min-w-0 rounded-xl border border-slate-100 bg-slate-50 p-4"><div class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Target penjualan</div><div class="mt-1.5 break-words text-sm font-black text-ink" x-text="review?.target"></div></div>
                    <div class="min-w-0 rounded-xl border border-emerald-100 bg-emerald-50 p-4"><div class="text-[9px] font-extrabold uppercase tracking-wider text-emerald-500">Realisasi Closed Won</div><div class="mt-1.5 break-words text-sm font-black text-emerald-700" x-text="review?.realization"></div></div>
                    <div class="col-span-2 min-w-0 rounded-xl border border-brand-100 bg-brand-50 p-4"><div class="text-[9px] font-extrabold uppercase tracking-wider text-brand-500">Pencapaian</div><div class="mt-1.5 text-lg font-black text-brand-700" x-text="review?.achievement"></div></div>
                </div>
                <div class="mt-4">
                    <div class="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">KPI Operasional Sales · Realisasi / Target</div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-xl bg-blue-50 p-3"><div class="text-[8px] font-extrabold uppercase text-blue-500">Total NOO</div><div class="mt-1 text-xs font-black text-blue-800" x-text="review?.noo"></div></div>
                        <div class="rounded-xl bg-emerald-50 p-3"><div class="text-[8px] font-extrabold uppercase text-emerald-500">NOO Custom</div><div class="mt-1 text-xs font-black text-emerald-800" x-text="review?.customNoo"></div></div>
                        <div class="rounded-xl bg-sky-50 p-3"><div class="text-[8px] font-extrabold uppercase text-sky-500">Drink</div><div class="mt-1 text-xs font-black text-sky-800" x-text="review?.drinkVolume"></div></div>
                        <div class="rounded-xl bg-lime-50 p-3"><div class="text-[8px] font-extrabold uppercase text-lime-600">Food</div><div class="mt-1 text-xs font-black text-lime-800" x-text="review?.foodVolume"></div></div>
                    </div>
                </div>
                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Catatan Head / Manager</div>
                    <p class="mt-2 min-h-10 whitespace-pre-line text-sm leading-relaxed text-slate-700" x-text="review?.notes"></p>
                    <div x-show="review?.hasComment && review?.commenter" class="mt-4 flex items-end justify-between gap-4 border-t border-slate-100 pt-3">
                        <div class="min-w-0"><div class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Commented by</div><div class="mt-1 truncate text-xs font-black text-slate-700" x-text="review?.commenter"></div></div>
                        <div class="shrink-0 text-right text-[9px] text-slate-400" x-text="review?.commentedAt"></div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end border-t border-slate-100 px-6 py-4"><button type="button" class="btn-secondary" @click="review = null">Tutup</button></div>
        </div>
    </div>

    <div x-show="selected" x-cloak class="fixed inset-0 z-[150] grid place-items-center bg-slate-950/45 p-4" @keydown.escape.window="selected = null">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl" @click.outside="selected = null">
            <form x-bind:action="selected ? '{{ url('/kpi') }}/' + selected.id : '#'" method="POST">
                @csrf @method('PUT')
                <input type="hidden" name="period" value="{{ $from->format('Y-m') }}">
                <div class="border-b border-slate-100 px-6 py-5"><h3 class="text-lg font-black text-ink">Atur KPI <span x-text="selected?.name"></span></h3><p class="mt-1 text-xs text-slate-400">Target dan masukan untuk {{ $from->translatedFormat('F Y') }}</p></div>
                <div class="space-y-4 p-6">
                    <label class="block"><span class="mb-1.5 block text-xs font-bold text-slate-600">Target penjualan</span><div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span><input class="field !pl-9" type="text" inputmode="numeric" data-money name="sales_target" x-model="selected.target" required></div><span class="mt-1 block text-[10px] text-slate-400">Masukkan target omzet sales untuk bulan ini.</span></label>
                    <div><div class="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Target Akuisisi</div><div class="grid grid-cols-2 gap-3"><label><span class="mb-1 block text-[10px] font-bold text-slate-500">Total NOO</span><input class="field" type="number" min="0" name="noo_target" x-model="selected.nooTarget" required></label><label><span class="mb-1 block text-[10px] font-bold text-slate-500">NOO Custom</span><input class="field" type="number" min="0" name="custom_noo_target" x-model="selected.customNooTarget" required></label></div></div>
                    <div><div class="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Target Volume</div><div class="grid grid-cols-2 gap-3"><label><span class="mb-1 block text-[10px] font-bold text-slate-500">Drink Market (pcs)</span><input class="field" type="number" min="0" name="drink_volume_target" x-model="selected.drinkVolumeTarget" required></label><label><span class="mb-1 block text-[10px] font-bold text-slate-500">Food Market (pcs)</span><input class="field" type="number" min="0" name="food_volume_target" x-model="selected.foodVolumeTarget" required></label></div></div>
                    <div class="rounded-xl border border-brand-100 bg-brand-50/60 px-4 py-3">
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-brand-500">Perkiraan pencapaian</div>
                        <div class="mt-1 text-lg font-black text-brand-700" x-text="(() => { const target = Number(String(selected?.target || '').replace(/\D/g, '')); return target > 0 ? ((Number(selected?.realization || 0) / target) * 100).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%' : '0,0%'; })()"></div>
                        <div class="mt-1 text-[10px] text-brand-600">Realisasi Closed Won ÷ target penjualan × 100%.</div>
                    </div>
                    <label class="block"><span class="mb-1.5 block text-xs font-bold text-slate-600">Catatan Head / Manager</span><textarea class="field min-h-24" name="evaluation_notes" x-model="selected.notes"></textarea></label>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4"><button type="button" class="btn-secondary" @click="selected = null">Batal</button><button class="btn-primary">Simpan KPI</button></div>
            </form>
        </div>
    </div>
</div>

<div class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-[11px] leading-relaxed text-indigo-800">
    <strong>Cara baca:</strong> target tetap memakai nilai bulanan penuh pada setiap bulan yang tercakup. Closing menunjukkan jumlah opportunity yang menjadi <strong>Closed Won</strong>, bukan jumlah produk. Realisasi dan closing mengikuti rentang tanggal. Pencapaian = realisasi ÷ target.
</div>
@endsection
