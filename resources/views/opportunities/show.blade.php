@extends('layouts.app')
@section('title',$opportunity->title)
@section('eyebrow','Opportunity · '.$opportunity->opportunity_id)
@section('page-actions')
<div class="hidden gap-2 md:flex"><a href="{{ route('activities.create',['customer'=>$opportunity->customer_id,'opportunity'=>$opportunity]) }}" class="btn-primary">+ Catat aktivitas</a><a href="{{ route('tasks.create',['customer'=>$opportunity->customer_id,'opportunity'=>$opportunity]) }}" class="btn-secondary">+ Task</a></div>
@endsection
@section('content')
@php
    $productPhotoRequired = \App\Models\SystemSetting::bool('opportunity_product_photo_required', true, auth()->user());
@endphp
<div class="card overflow-hidden">
    <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0"><div class="mb-2 flex flex-wrap items-center gap-2"><span class="badge border" style="color:{{ $opportunity->stage->color }};border-color:{{ $opportunity->stage->color }}40;background:{{ $opportunity->stage->color }}10"><span class="size-1.5 rounded-full" style="background:{{ $opportunity->stage->color }}"></span>{{ $opportunity->stage->name }}</span><span class="badge {{ $opportunity->priority === 'high' ? 'bg-rose-50 text-rose-600 ring-1 ring-rose-100' : ($opportunity->priority === 'medium' ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-100' : 'bg-sky-50 text-sky-600 ring-1 ring-sky-100') }}">Priority {{ ['low'=>'Low','medium'=>'Medium','high'=>'High'][$opportunity->priority] ?? ucfirst($opportunity->priority) }}</span></div><a href="{{ route('customers.show',$opportunity->customer) }}" class="text-xs font-bold text-brand-600 hover:text-brand-700">{{ $opportunity->customer->company_name }} →</a></div>
        <div class="grid grid-cols-2 gap-x-7 gap-y-3 sm:grid-cols-4">
            @foreach([['Tahap',$opportunity->stage->name],['Sales',$opportunity->owner->name],['Nilai target','Rp '.number_format($opportunity->estimated_value,0,',','.')],['Target closing',$opportunity->expected_close_date?->translatedFormat('d M Y') ?? 'Belum diatur']] as [$label,$value])
            <div><div class="text-[9px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</div><div class="mt-1 max-w-32 truncate text-[11px] font-extrabold text-ink">{{ $value }}</div></div>
            @endforeach
        </div>
    </div>
    <div class="scrollbar-thin flex gap-6 overflow-x-auto border-b border-slate-100 px-5 text-[11px] font-bold text-slate-500"><a href="#ringkasan" class="border-b-2 border-brand-600 py-3 text-brand-600">Ringkasan</a><a href="#pekerjaan" class="py-3 hover:text-brand-600">Task ({{ $opportunity->tasks->count() }})</a><a href="#aktivitas" class="py-3 hover:text-brand-600">Aktivitas ({{ $opportunity->activities->count() }})</a><a href="#timeline" class="py-3 hover:text-brand-600">Riwayat tahap</a></div>
</div>

<section class="card mt-4 overflow-hidden" x-data="{ lostOpen: {{ $errors->has('lost_reason') || $errors->has('reason') ? 'true' : 'false' }}, lostStageId: '{{ old('stage_id') }}' }">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5"><div><h3 class="section-title">Progres pipeline</h3><p class="mt-1 text-[10px] text-slate-400">Pilih tahap untuk memindahkan opportunity</p></div><span class="text-[10px] font-semibold text-slate-400">{{ $opportunity->days_in_stage }} hari pada tahap ini</span></div>
    <style>
        .stage-arrows > div { height: 4rem; background: transparent !important; }
        .stage-arrows > div::before { content: ''; position: absolute; inset: 0 0 auto; height: 3rem; background: var(--stage-bg, #f1f5f9); clip-path: polygon(10px 0, calc(100% - 10px) 0, 100% 50%, calc(100% - 10px) 100%, 10px 100%, 20px 50%); }
        .stage-arrows > div:first-child::before { clip-path: polygon(0 0, calc(100% - 10px) 0, 100% 50%, calc(100% - 10px) 100%, 0 100%); }
        .stage-arrows > div.is-active { --stage-bg: #4f46e5; }
        .stage-arrows > div.is-completed { --stage-bg: #d1fae5; }
        .stage-arrows > div > div.absolute { display: none; }
        .stage-arrows > div > form { position: absolute; inset: 0 0 auto; z-index: 20; height: 3rem; }
        .stage-arrows > div > form button { width: 100%; height: 100%; opacity: 0; }
        .stage-arrows > div > div:nth-last-child(2) { position: absolute; inset: 0 12px auto; z-index: 10; display: flex; height: 3rem; align-items: center; justify-content: center; margin: 0; max-width: none; overflow: hidden; color: inherit; text-overflow: ellipsis; white-space: nowrap; }
        .stage-arrows > div > div:last-child { position: absolute; inset: auto 0 0; display: block; color: #94a3b8; }
        .stage-arrows > div.is-active > div:last-child { color: #4f46e5; font-weight: 700; }
        .stage-arrows > div::before { transition: filter .15s ease; }
        .stage-arrows > div:hover::before { filter: brightness(.97); }
    </style>
    <div class="scrollbar-thin overflow-x-auto px-5 py-5">
        <div class="stage-arrows flex min-w-[820px] items-stretch gap-1">
        @foreach($opportunity->pipeline->stages as $stage)
            @php
                $currentPosition = $opportunity->stage->position;
                $completed = $stage->position < $currentPosition;
                $active = $stage->id === $opportunity->pipeline_stage_id;
            @endphp
            <div class="relative flex h-12 flex-1 items-center justify-center text-center {{ $active ? 'is-active bg-brand-600 text-white' : ($completed ? 'is-completed bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500') }}">
                @if(!$loop->first)<div class="absolute right-1/2 top-4 h-0.5 w-full {{ $completed || $active ? 'bg-brand-500' : 'bg-slate-200' }}"></div>@endif
                @if($stage->is_lost && !$active)
                <button type="button" @click="lostStageId='{{ $stage->id }}'; lostOpen=true" title="Pindahkan ke {{ $stage->name }}" class="absolute inset-x-0 top-0 z-20 h-12 opacity-0">Pindahkan ke {{ $stage->name }}</button>
                @else
                <form method="POST" action="{{ route('opportunities.stage',$opportunity) }}" class="relative z-10">@csrf<input type="hidden" name="stage_id" value="{{ $stage->id }}"><input type="hidden" name="reason" value="Dipindahkan melalui halaman detail opportunity"><button title="Pindahkan ke {{ $stage->name }}" class="grid size-8 place-items-center rounded-full border-2 text-[10px] font-black transition {{ $active ? 'border-brand-600 bg-brand-600 text-white ring-4 ring-brand-100' : ($completed ? 'border-brand-500 bg-white text-brand-600' : 'border-slate-200 bg-white text-slate-300 hover:border-brand-300 hover:text-brand-500') }}">{{ $active ? '●' : ($completed ? '✓' : $loop->iteration) }}</button></form>
                @endif
                <div class="relative z-10 mt-3 max-w-24 truncate text-[10px] font-bold {{ $active ? 'text-brand-600' : ($completed ? 'text-slate-600' : 'text-slate-400') }}">{{ $stage->name }}</div><div class="mt-1 text-[9px] text-slate-400">{{ $stage->probability }}%</div>
            </div>
        @endforeach
        </div>
    </div>
    <div x-show="lostOpen" x-cloak x-transition.opacity @keydown.escape.window="lostOpen=false" @click.self="lostOpen=false" class="fixed inset-0 z-[130] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
        <form method="POST" action="{{ route('opportunities.stage', $opportunity) }}" class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
            @csrf
            <input type="hidden" name="stage_id" :value="lostStageId">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Tandai sebagai Lost</h3><p class="mt-1 text-[10px] text-slate-400">Catat alasan agar hasil opportunity dapat dievaluasi.</p></div><button type="button" @click="lostOpen=false" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Tutup">×</button></div>
            <div class="space-y-4 p-5"><label class="block"><span class="label">Kategori alasan *</span><select name="lost_reason" class="field mt-2" required><option value="">Pilih alasan Lost</option>@foreach(['price'=>'Harga','competitor'=>'Kompetitor','budget'=>'Anggaran','cancelled'=>'Kebutuhan dibatalkan','no_response'=>'Tidak ada respons','other'=>'Lainnya'] as $value=>$label)<option value="{{ $value }}" @selected(old('lost_reason')===$value)>{{ $label }}</option>@endforeach</select>@error('lost_reason')<p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $message }}</p>@enderror</label><label class="block"><span class="label">Detail alasan *</span><textarea name="reason" rows="4" class="field mt-2" placeholder="Jelaskan penyebab opportunity tidak dilanjutkan" required>{{ old('reason') }}</textarea>@error('reason')<p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $message }}</p>@enderror</label></div>
            <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4"><button type="button" @click="lostOpen=false" class="btn-secondary">Batal</button><button class="btn-primary !bg-rose-600 hover:!bg-rose-700">Tandai sebagai Lost</button></div>
        </form>
    </div>
</section>

<div id="ringkasan" class="mt-4 grid scroll-mt-24 gap-4 xl:grid-cols-[1fr_360px]">
    <div class="space-y-4">
        <section class="card overflow-hidden" x-data="{ editGeneralInfo: false }">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 class="section-title">Informasi umum</h3>
                </div>
                <button type="button" @click="editGeneralInfo = true" class="btn-secondary !h-8 !px-3 text-[10px]">Edit informasi</button>
            </div>
            @php
                $infoProducts = $opportunity->items;
                $targetTotal = $infoProducts->sum(fn ($item) => (float) $item->quantity * (float) ($item->target_price ?? 0));
                $offeredTotal = $infoProducts->sum(fn ($item) => (float) ($item->subtotal ?? 0));
            @endphp
            @if(false)
            <div class="grid gap-x-8 gap-y-5 p-5 sm:grid-cols-2 lg:grid-cols-3">
                <div><div class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Customer</div><div class="mt-1.5 text-xs font-semibold text-slate-700">{{ $opportunity->customer->company_name }}</div></div>
                <div><div class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Nilai target opportunity</div><div class="mt-1.5 text-xs font-semibold text-slate-700">{{ $targetTotal > 0 ? 'Rp '.number_format($targetTotal,0,',','.') : 'Belum diatur' }}</div></div>
                <div><div class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Total harga penawaran</div><div class="mt-1.5 text-xs font-semibold text-slate-700">{{ $offeredTotal > 0 ? 'Rp '.number_format($offeredTotal,0,',','.') : 'Belum diatur' }}</div></div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <div class="flex items-center gap-2"><div class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Produk</div><span class="rounded-full bg-sky-50 px-2 py-0.5 text-[9px] font-bold text-sky-600">{{ $infoProducts->count() }} produk</span></div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse($infoProducts->take(3) as $item)
                            <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[10px] font-semibold text-slate-700">{{ $item->product_name }} <span class="text-slate-400">· {{ number_format($item->quantity,0,',','.') }} {{ \App\Models\Opportunity::QUANTITY_UNITS[$item->quantity_unit] ?? ucfirst($item->quantity_unit) }}</span></span>
                        @empty
                            <span class="text-xs font-semibold text-slate-400">Belum ada produk</span>
                        @endforelse
                        @if($infoProducts->count() > 3)<a href="#daftar-produk" class="rounded-lg bg-brand-50 px-2.5 py-1.5 text-[10px] font-bold text-brand-600 hover:bg-brand-100">+{{ $infoProducts->count() - 3 }} produk lainnya</a>@endif
                    </div>
                </div>

                @foreach([['Supplier saat ini',$opportunity->current_supplier],['Kompetitor',$opportunity->competitor]] as [$label,$value])
                    <div><div class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">{{ $label }}</div><div class="mt-1.5 text-xs font-semibold text-slate-700">{{ $value ?: 'Belum diisi' }}</div></div>
                @endforeach
            </div>

            @endif

            <div class="grid gap-x-8 gap-y-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach([['Customer',$opportunity->customer->company_name],['Supplier saat ini',$opportunity->current_supplier],['Kompetitor',$opportunity->competitor]] as [$label,$value])
                    <div>
                        <div class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">{{ $label }}</div>
                        <div class="mt-1.5 text-xs font-semibold text-slate-700">{{ $value ?: 'Belum diisi' }}</div>
                    </div>
                @endforeach
            </div>

            <div x-show="editGeneralInfo" x-cloak x-transition.opacity @keydown.escape.window="editGeneralInfo=false" @click.self="editGeneralInfo=false" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
                <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl" @click.stop>
                    <form method="POST" action="{{ route('opportunities.general-info', $opportunity) }}">
                        @csrf @method('PATCH')
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div><h3 class="section-title">Edit informasi opportunity</h3><p class="mt-1 text-[10px] text-slate-400">Perbarui informasi pendukung tanpa mengubah produk atau tahap penjualan.</p></div>
                            <button type="button" @click="editGeneralInfo=false" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Tutup">
                                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg>
                            </button>
                        </div>
                        <div class="grid gap-4 p-5 sm:grid-cols-2">
                            <label class="block"><span class="label">Supplier saat ini</span><input name="current_supplier" value="{{ old('current_supplier', $opportunity->current_supplier) }}" class="field mt-2" placeholder="Nama supplier yang digunakan"></label>
                            <label class="block"><span class="label">Kompetitor</span><input name="competitor" value="{{ old('competitor', $opportunity->competitor) }}" class="field mt-2" placeholder="Nama kompetitor jika ada"></label>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4">
                            <button type="button" @click="editGeneralInfo=false" class="btn-secondary">Batal</button>
                            <button class="btn-primary">Simpan perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section id="daftar-produk" class="card scroll-mt-24 overflow-hidden" x-data="{ addProduct: false, editing: null, historyOpen: false }">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h3 class="section-title">Daftar produk</h3><p class="mt-1 text-[10px] text-slate-400">Status keputusan dicatat untuk setiap produk</p></div>
                <div class="flex flex-wrap items-center gap-2 text-[10px] font-bold">
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">{{ $opportunity->items->where('deal_status', 'on_process')->count() }} diproses</span>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">{{ $opportunity->items->where('deal_status', 'deal')->count() }} deal</span>
                    <span class="rounded-full bg-rose-50 px-2.5 py-1 text-rose-700">{{ $opportunity->items->where('deal_status', 'rejected')->count() }} ditolak</span>
                    <button type="button" @click="addProduct = true" class="btn-secondary !h-8 !px-3 text-[10px]">+ Tambah produk</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[920px] text-left text-xs">
                    <thead class="table-head"><tr><th class="px-5 py-3">Produk</th><th class="px-4 py-3 text-right">Est. Qty/Bulan</th><th class="px-4 py-3 text-right">Target Harga per UOM</th><th class="px-4 py-3 text-right">Harga Penawaran per UOM</th><th class="px-4 py-3 text-right">Subtotal</th><th class="px-5 py-3">Status barang</th><th class="w-20 px-4 py-3 text-center">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($opportunity->items as $item)
                            <tr x-data="{ editPrice: false }">
                                <td class="px-5 py-3.5"><div class="flex items-center gap-3">@if($item->photo_path)<a href="{{ Storage::disk('public')->url($item->photo_path) }}" data-evidence-lightbox data-evidence-name="Foto produk · {{ $item->product_name }}" class="group relative" title="Preview foto {{ $item->product_name }}"><img src="{{ Storage::disk('public')->url($item->photo_path) }}" alt="Foto {{ $item->product_name }}" class="size-12 rounded-lg border border-slate-200 object-cover transition group-hover:brightness-75"><span class="pointer-events-none absolute inset-0 grid place-items-center text-base text-white opacity-0 transition group-hover:opacity-100">⌕</span></a>@else<div class="grid size-12 place-items-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-[8px] font-bold text-slate-400">Belum ada foto</div>@endif<div class="font-bold text-ink">{{ $item->product_name }}</div></div></td>
                                <td class="px-4 py-3.5 text-right font-semibold">{{ number_format($item->quantity,0,',','.') }} {{ \App\Models\Opportunity::QUANTITY_UNITS[$item->quantity_unit] ?? ucfirst($item->quantity_unit) }}</td>
                                <td class="px-4 py-3.5 text-right text-slate-500">{{ $item->target_price !== null ? 'Rp '.number_format($item->target_price,0,',','.') : '—' }}</td>
                                <td class="px-4 py-3.5 text-right">
                                    <button x-show="!editPrice" type="button" @click="editPrice=true; $nextTick(() => $refs.price.focus())" class="font-semibold text-brand-600 hover:text-brand-700">{{ (float) $item->unit_price > 0 ? 'Rp '.number_format($item->unit_price,0,',','.') : '+ Atur harga' }}</button>
                                    <form x-show="editPrice" x-cloak method="POST" action="{{ route('opportunities.items.price', [$opportunity, $item]) }}" class="ml-auto flex w-40 items-center gap-1">@csrf @method('PATCH')<input x-ref="price" name="unit_price" data-money inputmode="numeric" value="{{ number_format((float) $item->unit_price,0,',','.') }}" class="field !h-8 !px-2 text-right text-[10px]" required><button class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-600 text-white" title="Simpan harga">✓</button></form>
                                </td>
                                <td class="px-4 py-3.5 text-right font-extrabold text-ink">Rp {{ number_format($item->subtotal,0,',','.') }}</td>
                                <td class="px-5 py-3.5"><div class="inline-flex overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">@foreach(['on_process' => ['Diproses', '○', 'bg-amber-50 text-amber-700'], 'deal' => ['Deal', '✓', 'bg-emerald-50 text-emerald-700'], 'rejected' => ['Ditolak', '×', 'bg-rose-50 text-rose-700']] as $status => [$label, $icon, $activeClass])<form method="POST" action="{{ route('opportunities.items.status', [$opportunity, $item]) }}">@csrf @method('PATCH')<input type="hidden" name="deal_status" value="{{ $status }}"><button class="flex h-8 items-center gap-1.5 border-r border-slate-200 px-2.5 text-[10px] font-bold last:border-r-0 {{ $item->deal_status === $status ? $activeClass : 'text-slate-400 hover:bg-slate-50 hover:text-slate-700' }}" title="Tandai {{ strtolower($label) }}"><span class="text-sm leading-none">{{ $icon }}</span><span>{{ $label }}</span></button></form>@endforeach</div></td>
                                <td class="px-4 py-3.5 text-center"><button type="button" @click="editing = {{ $item->id }}" class="btn-secondary !h-8 !px-3 text-[10px]" aria-label="Edit {{ $item->product_name }}">Edit</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-8 text-center text-slate-400">Belum ada rincian produk.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-3">
                <button type="button" @click="historyOpen = true" class="text-[10px] font-bold text-slate-500 hover:text-brand-600">Riwayat perubahan produk ({{ $productHistory->count() }})</button>
            </div>

            <div x-show="addProduct" x-cloak x-transition.opacity @keydown.escape.window="addProduct=false" @click.self="addProduct=false" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
                <form method="POST" action="{{ route('opportunities.items.store', $opportunity) }}" enctype="multipart/form-data" x-data="{ photoPreview: null, previewPhoto(event) { if (this.photoPreview) URL.revokeObjectURL(this.photoPreview); this.photoPreview = event.target.files[0] ? URL.createObjectURL(event.target.files[0]) : null } }" class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    @csrf
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Tambah produk</h3><p class="mt-1 text-[10px] text-slate-400">Tambahkan kebutuhan produk customer.</p></div><button type="button" @click="addProduct=false" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Tutup"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button></div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        <div class="sm:col-span-2"><label class="label">Nama produk *</label><input name="product_name" class="field" placeholder="Contoh: Paper bowl 12 oz" required></div><div><label class="label">Market *</label><select name="market_segment" class="field" required><option value="drink">Drink Market</option><option value="food">Food Market</option></select></div>
                        <div><label class="label">Est. Qty/Bulan *</label><input name="quantity" type="number" min="1" value="1" class="field" required></div>
                        <div><label class="label">UOM *</label><select name="quantity_unit" class="field">@foreach(\App\Models\Opportunity::QUANTITY_UNITS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                        <div><label class="label">Target Harga per UOM</label><div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span><input name="target_price" data-money inputmode="numeric" class="field !pl-9" placeholder="0"></div></div>
                        <div><label class="label">Harga penawaran per UOM</label><div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span><input name="unit_price" data-money inputmode="numeric" class="field !pl-9" placeholder="0"></div><p class="mt-1 text-[10px] text-slate-400">Isi harga untuk 1 satuan sesuai UOM produk, bukan harga total.</p></div>
                        <p class="sm:col-span-2 text-[10px] text-slate-400">Produk baru otomatis berstatus Diproses.</p>
                        <div class="sm:col-span-2 border-t border-slate-200 pt-4"><label class="label">Foto produk{{ $productPhotoRequired ? ' *' : ' (opsional)' }}</label><div class="flex items-start gap-3"><img x-show="photoPreview" x-cloak :src="photoPreview" alt="Preview foto produk" class="size-20 shrink-0 rounded-lg border border-slate-200 object-cover"><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" @change="previewPhoto($event)" class="field file:mr-3 file:border-0 file:bg-transparent file:text-xs file:font-bold file:text-brand-600" @required($productPhotoRequired)></div><p class="mt-1 text-[10px] text-slate-400">Preview akan tampil setelah foto dipilih. JPG, PNG, atau WebP maksimal 5 MB; lokasi tidak digunakan.</p></div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4"><button type="button" @click="addProduct=false" class="btn-secondary">Batal</button><button class="btn-primary">Tambah produk</button></div>
                </form>
            </div>

            @foreach($opportunity->items as $item)
                <div x-show="editing === {{ $item->id }}" x-cloak x-transition.opacity @keydown.escape.window="editing=null" @click.self="editing=null" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
                    <form method="POST" action="{{ route('opportunities.items.update', [$opportunity, $item]) }}" enctype="multipart/form-data" x-data="{ photoPreview: @js($item->photo_path ? Storage::disk('public')->url($item->photo_path) : null), previewPhoto(event) { this.photoPreview = event.target.files[0] ? URL.createObjectURL(event.target.files[0]) : null } }" class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">@csrf @method('PATCH')
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Edit produk</h3><p class="mt-1 text-[10px] text-slate-400">Perbarui informasi {{ $item->product_name }}.</p></div><button type="button" @click="editing=null" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Tutup"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button></div>
                        <div class="grid gap-4 p-5 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label class="label">Nama produk *</label><input name="product_name" class="field" value="{{ $item->product_name }}" required></div><div><label class="label">Market *</label><select name="market_segment" class="field" required><option value="drink" @selected($item->market_segment==='drink')>Drink Market</option><option value="food" @selected($item->market_segment==='food')>Food Market</option></select></div>
                            <div><label class="label">Est. Qty/Bulan *</label><input name="quantity" type="number" min="1" class="field" value="{{ $item->quantity }}" required></div>
                            <div><label class="label">UOM *</label><select name="quantity_unit" class="field">@foreach(\App\Models\Opportunity::QUANTITY_UNITS as $value => $label)<option value="{{ $value }}" @selected($item->quantity_unit === $value)>{{ $label }}</option>@endforeach</select></div>
                            <div><label class="label">Target Harga per UOM</label><div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span><input name="target_price" data-money inputmode="numeric" class="field !pl-9" value="{{ number_format((float) $item->target_price,0,',','.') }}"></div></div>
                            <div><label class="label">Harga penawaran per UOM</label><div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span><input name="unit_price" data-money inputmode="numeric" class="field !pl-9" value="{{ number_format((float) $item->unit_price,0,',','.') }}"></div><p class="mt-1 text-[10px] text-slate-400">Isi harga untuk 1 satuan sesuai UOM produk, bukan harga total.</p></div>
                            <div class="sm:col-span-2 border-t border-slate-200 pt-4"><label class="label">Foto produk{{ $productPhotoRequired && !$item->photo_path ? ' *' : ' (opsional)' }}</label><div class="flex items-start gap-3"><img x-show="photoPreview" :src="photoPreview" alt="Preview foto {{ $item->product_name }}" class="size-20 shrink-0 rounded-lg border border-slate-200 object-cover"><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" @change="previewPhoto($event)" class="field file:mr-3 file:border-0 file:bg-transparent file:text-xs file:font-bold file:text-brand-600" @required($productPhotoRequired && !$item->photo_path)></div></div>
                        </div>
                        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4"><button type="button" @click="editing=null" class="btn-secondary">Batal</button><button class="btn-primary">Simpan perubahan</button></div>
                    </form>
                </div>
            @endforeach

            <div x-show="historyOpen" x-cloak x-transition.opacity @keydown.escape.window="historyOpen=false" @click.self="historyOpen=false" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
                <div class="flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Riwayat perubahan produk</h3><p class="mt-1 text-[10px] text-slate-400">Catatan penambahan dan perubahan produk.</p></div><button type="button" @click="historyOpen=false" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Tutup"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button></div>
                    <div class="overflow-y-auto p-5"><div class="space-y-2">
                    @forelse($productHistory as $history)
                        @php
                            $historyItem = $opportunity->items->firstWhere('id', $history->auditable_id);
                            $historyProductName = $historyItem?->product_name
                                ?? data_get($history->new_values, 'product_name')
                                ?? data_get($history->old_values, 'product_name')
                                ?? 'Produk tidak ditemukan';
                            $fieldLabels = ['product_name'=>'Nama produk','quantity'=>'Est. Qty/Bulan','quantity_unit'=>'UOM','target_price'=>'Target harga','unit_price'=>'Harga penawaran','deal_status'=>'Status','photo_path'=>'Foto produk'];
                            $statusLabels = ['on_process'=>'Diproses','deal'=>'Deal','rejected'=>'Ditolak'];
                            $changes = collect($fieldLabels)->map(function($label, $key) use ($history, $statusLabels) {
                                $newValues = $history->new_values ?? [];
                                if ($history->action === 'created' || !array_key_exists($key, $newValues)) return null;
                                $old = data_get($history->old_values, $key); $new = data_get($history->new_values, $key);
                                if ((string)$old === (string)$new) return null;
                                if ($key === 'photo_path') return 'Foto produk diperbarui';
                                if ($key === 'deal_status') { $old = $statusLabels[$old] ?? $old; $new = $statusLabels[$new] ?? $new; }
                                if (in_array($key, ['target_price','unit_price'], true)) { $old = 'Rp '.number_format((float)$old,0,',','.'); $new = 'Rp '.number_format((float)$new,0,',','.'); }
                                return $label.': '.(($old === null || $old === '') ? '—' : $old).' → '.(($new === null || $new === '') ? '—' : $new);
                            })->filter();
                        @endphp
                        <div class="flex flex-col gap-2 rounded-lg bg-slate-50 px-3 py-2.5 text-[10px] sm:flex-row sm:items-start sm:justify-between"><div><div class="font-bold text-ink">{{ $historyProductName }}</div><div class="mt-0.5 text-slate-500">{{ $history->action === 'created' ? 'Produk ditambahkan' : ($changes->join(' · ') ?: 'Informasi teknis diperbarui') }}</div></div><div class="shrink-0 text-slate-400">{{ $history->created_at->translatedFormat('d M Y, H:i') }} · {{ $history->user?->name ?? 'Sistem' }}</div></div>
                    @empty
                        <p class="text-[10px] text-slate-400">Belum ada perubahan produk.</p>
                    @endforelse
                    </div></div>
                </div>
            </div>
        </section>

        @if(false)
        <section class="hidden">
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="section-title">Harga quotation</h3>
                    <p class="mt-1 text-xs text-slate-400">Harga penawaran dapat direvisi tanpa mengubah nilai target awal.</p>
                </div>
                @if(!$canSetQuotation)
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-[10px] font-bold text-slate-600">{{ $opportunity->stage->slug === 'quotation' ? 'Harga tidak dapat diubah' : 'Harga hanya diedit di tahap Quotation' }}</span>
                @endif
            </div>

            @if($canSetQuotation)
                <form method="POST" action="{{ route('opportunities.quotation', $opportunity) }}" class="p-5">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-3">
                        @foreach($opportunity->items as $item)
                            <div class="grid items-end gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4 sm:grid-cols-[1fr_160px]">
                                <div>
                                    <div class="text-xs font-extrabold text-ink">{{ $item->product_name }}</div>
                                    <div class="mt-1 text-[10px] text-slate-400">{{ number_format($item->quantity,0,',','.') }} {{ \App\Models\Opportunity::QUANTITY_UNITS[$item->quantity_unit] ?? ucfirst($item->quantity_unit) }} · Target harga per UOM {{ $item->target_price !== null ? 'Rp '.number_format($item->target_price,0,',','.') : 'belum diisi' }}</div>
                                </div>
                                <div>
                                    <input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}">
                                    <label class="label">Harga penawaran per UOM</label>
                                    <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span><input type="text" inputmode="numeric" data-money class="field !pl-9" name="items[{{ $loop->index }}][unit_price]" value="{{ number_format((float) $item->unit_price,0,',','.') }}" required></div>
                                    <p class="mt-1 text-[10px] text-slate-400">Harga untuk 1 satuan sesuai UOM produk.</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex justify-end"><button class="btn-primary">Simpan harga penawaran</button></div>
                </form>
            @endif

            <div class="border-t border-slate-100 px-5 py-4">
                <h4 class="text-xs font-extrabold text-ink">Riwayat perubahan harga</h4>
                <div class="mt-3 space-y-2">
                    @forelse($quotationHistory as $history)
                        <div class="flex flex-col gap-2 rounded-lg bg-slate-50 px-3 py-2.5 text-xs sm:flex-row sm:items-center sm:justify-between">
                            <div><span class="font-bold text-ink">{{ data_get($history->new_values, 'product_name', 'Produk') }}</span><span class="ml-2 text-slate-500">Rp {{ number_format((float) data_get($history->old_values, 'unit_price', 0),0,',','.') }} → Rp {{ number_format((float) data_get($history->new_values, 'unit_price', 0),0,',','.') }}</span></div>
                            <div class="text-[10px] text-slate-400">{{ $history->created_at->translatedFormat('d M Y, H:i') }} · {{ $history->user?->name ?? 'Sistem' }}</div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">Belum ada revisi harga penawaran.</p>
                    @endforelse
                </div>
            </div>
        </section>
        @endif

        <section id="pekerjaan" class="card scroll-mt-24 overflow-hidden"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h3 class="section-title">Task terkait</h3><a href="{{ route('tasks.create',['customer'=>$opportunity->customer_id,'opportunity'=>$opportunity]) }}" class="text-[10px] font-bold text-brand-600">+ Tambah task</a></div><div class="divide-y divide-slate-100">@forelse($opportunity->tasks as $task)<div class="flex items-center gap-3 px-5 py-3.5"><span class="grid size-5 place-items-center rounded-md border {{ $task->status==='done' ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 text-slate-300' }} text-[9px]">✓</span><div class="min-w-0 flex-1"><div class="truncate text-xs font-bold text-ink">{{ $task->title }}</div><div class="mt-1 text-[9px] capitalize text-slate-400">{{ str_replace('_',' ',$task->status) }} · {{ $task->priority }}</div></div><span class="text-[9px] {{ $task->due_at?->isPast() && $task->status!=='done' ? 'font-bold text-rose-500' : 'text-slate-400' }}">{{ $task->due_at?->format('d M Y') ?? 'Tanpa batas waktu' }}</span></div>@empty<div class="empty-state">Belum ada task terkait.</div>@endforelse</div></section>

        <section id="aktivitas" class="card scroll-mt-24 overflow-hidden"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h3 class="section-title">Aktivitas</h3><a href="{{ route('activities.create',['customer'=>$opportunity->customer_id,'opportunity'=>$opportunity]) }}" class="text-[10px] font-bold text-brand-600">+ Catat aktivitas</a></div><div class="divide-y divide-slate-100">@forelse($opportunity->activities as $activity)<div class="flex gap-3 px-5 py-4"><div class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-[9px] font-black uppercase text-brand-600">{{ mb_substr($activity->type,0,2) }}</div><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-3"><div class="text-xs font-bold text-ink">{{ $activity->summary }}</div><time class="shrink-0 text-[9px] text-slate-400">{{ $activity->occurred_at->format('d M Y, H:i') }}</time></div><p class="mt-1 text-[11px] leading-relaxed text-slate-500">{{ $activity->result ?: ($activity->detail ?: 'Tidak ada catatan tambahan.') }}</p>@include('activities._attachments', ['activity' => $activity])<div class="mt-2 text-[9px] font-medium text-slate-400">Oleh {{ $activity->user->name }}</div></div></div>@empty<div class="empty-state">Belum ada aktivitas.</div>@endforelse</div></section>

    </div>

    <aside class="space-y-4">
        <section class="card overflow-hidden"><div class="border-b border-slate-100 px-5 py-4"><h3 class="section-title">Aksi berikutnya</h3></div><div class="p-5"><p class="text-xs font-semibold leading-relaxed text-slate-700">{{ $opportunity->next_action ?: 'Aksi berikutnya belum ditentukan.' }}</p><div class="mt-4 flex items-center gap-2 rounded-lg bg-slate-50 p-3"><span class="grid size-8 place-items-center rounded-lg bg-white text-xs shadow-sm">◷</span><div><div class="text-[9px] font-bold uppercase text-slate-400">Jadwal follow-up</div><div class="mt-1 text-[11px] font-bold text-ink">{{ $opportunity->next_follow_up_at?->translatedFormat('d M Y, H:i') ?? 'Belum dijadwalkan' }}</div></div></div></div></section>

        <section class="card overflow-hidden"><div class="border-b border-slate-100 px-5 py-4"><h3 class="section-title">Ringkasan nilai</h3></div><div class="divide-y divide-slate-100 px-5">@foreach([['Nilai target','Rp '.number_format($opportunity->estimated_value,0,',','.')],['Probabilitas',$opportunity->probability.'%'],['Nilai berbobot','Rp '.number_format($opportunity->weighted_value,0,',','.')]] as [$label,$value])<div class="flex items-center justify-between gap-3 py-3"><span class="text-[10px] text-slate-400">{{ $label }}</span><span class="text-xs font-extrabold text-ink">{{ $value }}</span></div>@endforeach</div></section>

        <section id="timeline" class="card scroll-mt-24 overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4"><h3 class="section-title">Riwayat tahap</h3></div>
            <div class="p-5">
                @forelse($opportunity->stageHistories->sortByDesc('created_at') as $history)
                    @php
                        $historyLostReason = data_get($history->validation_snapshot, 'lost_reason');
                        if (!$historyLostReason && $history->to_stage_id === $opportunity->pipeline_stage_id && $opportunity->status === 'lost') $historyLostReason = $opportunity->lost_reason;
                        $historyLostLabel = ['price'=>'Harga','competitor'=>'Kompetitor','budget'=>'Anggaran','cancelled'=>'Kebutuhan dibatalkan','no_response'=>'Tidak ada respons','other'=>'Lainnya'][$historyLostReason] ?? null;
                    @endphp
                    <div class="relative border-l border-slate-200 pb-5 pl-4 last:pb-0">
                        <span class="absolute -left-1 top-0 size-2 rounded-full bg-brand-500 ring-2 ring-white"></span>
                        <div class="text-[10px] font-extrabold text-slate-700">{{ $history->fromStage?->name ?? 'Tahap awal' }} → {{ $history->toStage?->name ?? 'Tahap diperbarui' }}</div>
                        @if($history->reason)
                            @if($historyLostLabel)
                                <div class="mt-1 space-y-1 rounded-md bg-rose-50/60 px-2.5 py-2 text-[9px] leading-relaxed">
                                    <div><span class="font-extrabold text-slate-500">Alasan Lost:</span> <span class="font-extrabold text-rose-600">{{ $historyLostLabel }}</span></div>
                                    <div><span class="font-extrabold text-slate-500">Catatan:</span> <span class="text-slate-700">{{ $history->reason }}</span></div>
                                </div>
                            @else
                                <div class="mt-1 rounded-md bg-slate-50 px-2 py-1.5 text-[9px] leading-relaxed text-slate-600">{{ $history->reason }}</div>
                            @endif
                        @endif
                        <div class="mt-1 text-[9px] font-semibold text-slate-500">Oleh {{ $history->changedBy?->name ?? 'Sistem' }}</div>
                        <div class="mt-0.5 text-[9px] text-slate-400">{{ $history->created_at->translatedFormat('d M Y, H:i') }}</div>
                    </div>
                @empty
                    <div class="text-center text-[10px] text-slate-400">Belum ada perpindahan tahap.</div>
                @endforelse
            </div>
        </section>
    </aside>
</div>

<div class="fixed inset-x-3 bottom-16 z-30 flex gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-xl md:hidden"><a href="{{ route('activities.create',['customer'=>$opportunity->customer_id,'opportunity'=>$opportunity]) }}" class="btn-secondary flex-1">+ Aktivitas</a><a href="{{ route('tasks.create',['customer'=>$opportunity->customer_id,'opportunity'=>$opportunity]) }}" class="btn-primary flex-1">+ Task</a></div>
@endsection
