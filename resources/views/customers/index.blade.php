@extends('layouts.app')
@section('title','Customer & Lead')
@section('eyebrow','CRM · Data relasi')
@section('page-actions')
<div class="flex gap-2">
    @if(auth()->user()->canAccess('leads.view'))<a href="{{ route('leads.create') }}" class="btn-secondary">+ Lead</a>@endif
</div>
@endsection
@section('content')

<section class="mb-4 rounded-2xl border border-indigo-100 bg-gradient-to-r from-indigo-50 to-white p-4">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-sm font-extrabold text-ink">Lead dan customer ada di satu tempat</h2>
            <p class="mt-1 text-xs text-slate-500">Mulai dari <b>Lead</b>. Jika sudah memenuhi kriteria, ubah menjadi <b>Customer</b>.</p>
        </div>
        <div class="flex items-center gap-2 text-[11px] text-slate-500">
            <span class="rounded-lg bg-white px-3 py-2 shadow-sm"><b class="text-amber-600">{{ $prospectCount }}</b> lead aktif</span>
            <span aria-hidden="true">→</span>
            <span class="rounded-lg bg-white px-3 py-2 shadow-sm"><b class="text-emerald-600">{{ $customerCount }}</b> customer</span>
        </div>
    </div>
</section>

<div class="mb-4 flex overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
    @if(auth()->user()->canAccess('leads.view'))
    <a href="{{ route('customers.index', ['view'=>'prospects']) }}" class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition {{ $view==='prospects'?'bg-amber-50 text-amber-700':'text-slate-500 hover:bg-slate-50' }}">
        Lead <span class="rounded-full bg-white px-2 py-0.5 text-[10px] shadow-sm">{{ $prospectCount }}</span>
    </a>
    @endif
    <a href="{{ route('customers.index', ['view'=>'customers']) }}" class="flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition {{ $view==='customers'?'bg-emerald-50 text-emerald-700':'text-slate-500 hover:bg-slate-50' }}">
        Customer <span class="rounded-full bg-white px-2 py-0.5 text-[10px] shadow-sm">{{ $customerCount }}</span>
    </a>
</div>

@php
    $legalNameRequired = \App\Models\SystemSetting::bool('customer_legal_name_required', true, auth()->user());
    $npwpRequired = \App\Models\SystemSetting::bool('customer_npwp_required', true, auth()->user());
    $customerFilterCount = collect([
        request('status'),
        request('area_id'),
        request('business_type'),
        request('owner_id'),
        request('follow_up'),
    ])->filter(fn ($value) => filled($value))->count();
@endphp
<form class="card relative z-20 mb-4 overflow-visible p-3" method="GET" x-data="{ filterOpen: false }" @keydown.escape.window="filterOpen=false">
    <input type="hidden" name="view" value="{{ $view }}">
    <div class="flex flex-col gap-2 sm:flex-row">
        <div class="relative min-w-0 flex-1">
            <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input class="field h-10 pl-9 text-sm" name="search" value="{{ request('search') }}" placeholder="Cari nama, nomor telepon, atau ID...">
        </div>
        <div class="flex gap-2">
            <button type="button" class="btn-secondary relative h-10 min-w-28" @click="filterOpen=!filterOpen">
                <svg class="mr-1.5 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                Filter
                @if($customerFilterCount)<span class="ml-1.5 grid size-5 place-items-center rounded-full bg-brand-600 text-[9px] font-black text-white">{{ $customerFilterCount }}</span>@endif
            </button>
            <button class="btn-primary h-10 min-w-20">Cari</button>
        </div>
    </div>

    <div x-show="filterOpen" x-cloak x-transition.origin.top.right @click.outside="filterOpen=false" class="absolute right-3 top-[58px] z-50 w-[min(760px,calc(100%-24px))] rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-extrabold text-ink">Filter {{ $view === 'prospects' ? 'lead' : 'customer' }}</h3>
                <p class="mt-0.5 text-[10px] text-slate-400">Pilih filter yang diperlukan saja.</p>
            </div>
            <button type="button" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200" @click="filterOpen=false" aria-label="Tutup filter">
                <svg class="block size-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3.5 3.5l9 9M12.5 3.5l-9 9"/></svg>
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Status</label>
                <x-scroll-select name="status" :selected="request('status', '')" :options="$view === 'prospects' ? \App\Models\Lead::EDITABLE_STATUSES : ['pareto'=>'Cust Pareto','active'=>'Cust Aktif','inactive'=>'Cust Non Aktif','risky'=>'Cust Risky']" placeholder="Semua status" />
            </div>
            <div>
                <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Area</label>
                <x-scroll-select name="area_id" :selected="request('area_id', '')" :options="$areas->mapWithKeys(fn ($area) => [(string) $area->id => $area->name])->all()" placeholder="Semua area" />
            </div>
            <div>
                <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Jenis Customer</label>
                <x-scroll-select name="business_type" :selected="request('business_type', '')" :options="$customerTypes->mapWithKeys(fn ($type) => [$type->name => $type->name])->all()" placeholder="Semua jenis customer" />
            </div>
            <div>
                <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Sales / PIC</label>
                <x-scroll-select name="owner_id" :selected="request('owner_id', '')" :options="$filterOwners->mapWithKeys(fn ($owner) => [(string) $owner->id => $owner->name])->all()" placeholder="Semua sales" />
            </div>
            <div>
                <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Tindak lanjut</label>
                <x-scroll-select name="follow_up" :selected="request('follow_up', '')" :options="['overdue'=>'Terlambat','scheduled'=>'Terjadwal','none'=>'Belum dijadwalkan']" placeholder="Semua tindak lanjut" />
            </div>
        </div>

        <div class="mt-4 flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('customers.index', ['view' => $view]) }}" class="btn-secondary min-w-32 justify-center">Reset</a>
            <button class="btn-primary min-w-32 justify-center">Terapkan</button>
        </div>
    </div>
</form>

<section class="card overflow-hidden">
    <header class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
        <div><b class="text-xs text-ink">{{ $records->total() }} {{ $view==='prospects'?'lead':'customer' }}</b><span class="ml-2 text-[10px] text-slate-400">Data {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }}</span></div>
        <span class="hidden text-[10px] text-slate-400 sm:block">Gunakan tombol Detail untuk membuka data lengkap</span>
    </header>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[940px] table-fixed border-collapse text-left">
            <thead class="bg-slate-100 text-[10px] font-extrabold uppercase tracking-wide text-slate-500">
                <tr class="border-b border-slate-300">
                    <th class="w-[230px] border-r border-slate-200 px-3 py-3">Perusahaan / Bisnis</th>
                    <th class="w-[170px] border-r border-slate-200 px-3 py-3">Kontak</th>
                    <th class="w-[145px] border-r border-slate-200 px-3 py-3">Area</th>
                    <th class="w-[155px] border-r border-slate-200 px-3 py-3">Sales</th>
                    @if($view === 'prospects')<th class="w-[140px] border-r border-slate-200 px-3 py-3">Tindak lanjut</th>@endif
                    <th class="w-[105px] border-r border-slate-200 px-3 py-3">Status</th>
                    <th class="w-[90px] px-3 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
            @forelse($records as $record)
                @php
                    $isProspect = $view === 'prospects';
                    $followUp = $record->next_follow_up_at;
                    $statusLabels = $isProspect
                        ? \App\Models\Lead::STATUSES
                        : ['pareto'=>'Cust Pareto','active'=>'Cust Aktif','inactive'=>'Cust Non Aktif','risky'=>'Cust Risky'];
                @endphp
                <tr class="transition hover:bg-indigo-50/40">
                    <td class="border-r border-slate-100 px-3 py-3">
                        <div class="truncate text-xs font-extrabold text-ink" title="{{ $isProspect ? $record->brand_name : $record->company_name }}">{{ $isProspect ? $record->brand_name : $record->company_name }}</div>
                        <div class="mt-1 truncate text-[10px] text-slate-400">{{ $isProspect?$record->lead_id:$record->customer_id }}@if($isProspect && $record->company_name) · {{ $record->company_name }}@elseif(!$isProspect && $record->brand_name) · {{ $record->brand_name }}@endif</div>
                    </td>
                    <td class="border-r border-slate-100 px-3 py-3">
                        @if($isProspect && $record->contact_name)<div class="truncate text-[11px] font-bold text-slate-700">{{ $record->contact_name }}</div>@endif
                        <div class="truncate text-[11px] {{ $isProspect&&$record->contact_name?'text-slate-500':'font-bold text-slate-700' }}">{{ $record->phone ?: 'Belum diisi' }}</div>
                        @if($record->email)<div class="mt-0.5 truncate text-[9px] text-slate-400">{{ $record->email }}</div>@endif
                    </td>
                    <td class="border-r border-slate-100 px-3 py-3"><div class="truncate text-[11px] font-semibold text-slate-600">{{ $record->area?->name ?? $record->city ?? 'Belum ditentukan' }}</div></td>
                    <td class="border-r border-slate-100 px-3 py-3">
                        @php($owner = $isProspect ? $record->owner : $record->salesOwner)
                        <div class="flex min-w-0 items-center gap-2"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-indigo-50 text-[9px] font-extrabold text-indigo-600">{{ $owner?mb_substr($owner->name,0,1):'?' }}</span><span class="truncate text-[11px] font-semibold text-slate-700">{{ $owner?->name ?? 'Belum ditentukan' }}</span></div>
                        @if($isProspect && $record->collaborators->isNotEmpty())<div class="mt-1 truncate pl-9 text-[9px] font-semibold text-brand-500" title="{{ $record->collaborators->pluck('name')->join(', ') }}">+ {{ $record->collaborators->count() }} kolaborator</div>@endif
                    </td>
                    @if($isProspect)
                    <td class="border-r border-slate-100 px-3 py-3 text-[10px] {{ $followUp?->isPast()?'font-bold text-rose-600':'text-slate-500' }}">
                            {{ $followUp?->translatedFormat('d M Y, H:i') ?? 'Belum dijadwalkan' }}
                            @if($followUp?->isPast())<div class="mt-0.5 text-[9px]">Terlambat</div>@endif
                    </td>
                    @endif
                    <td class="border-r border-slate-100 px-3 py-3"><span class="inline-flex rounded-full px-2 py-1 text-[10px] font-bold {{ in_array($record->status,['active','converted'])?'bg-emerald-50 text-emerald-700':($record->status === 'pareto'?'bg-violet-50 text-violet-700':(in_array($record->status,['leads_hold','leads_risky','risky'])?'bg-rose-50 text-rose-700':'bg-sky-50 text-sky-700')) }}">{{ $statusLabels[$record->status] ?? ucfirst($record->status) }}</span></td>
                    <td class="px-3 py-3 text-center">
                        @if($isProspect)
                            <div x-data="{open:false,convertOpen:false}" class="inline-block">
                                <button type="button" @click="open=true" class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-slate-600 shadow-sm">Aksi</button>
                                <template x-teleport="body">
                                    <div x-show="open" x-cloak @keydown.escape.window="open=false" class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/35 p-4" role="dialog" aria-modal="true">
                                        <div @click.outside="open=false" class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-2xl">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <h3 class="text-sm font-extrabold text-ink">Aksi lead</h3>
                                                    <p class="mt-1 text-xs text-slate-500">{{ $record->brand_name }}</p>
                                                </div>
                                                <button type="button" @click="open=false" class="grid size-8 place-items-center rounded-full bg-slate-100 text-sm text-slate-500 hover:bg-slate-200" aria-label="Tutup">×</button>
                                            </div>
                                            <div class="mt-5 grid gap-2">
                                                <a href="{{ route('leads.edit',$record) }}" class="btn-secondary justify-center">Lihat & edit lead</a>
                                                @if($record->status!=='converted')
                                                <button type="button" @click="open=false; $nextTick(() => convertOpen=true)" class="btn-primary w-full justify-center">Jadikan customer</button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                @if($record->status!=='converted')
                                <template x-teleport="body">
                                    <div x-show="convertOpen" x-cloak @keydown.escape.window="convertOpen=false" class="fixed inset-0 z-[110] grid place-items-center bg-slate-950/50 p-4" role="dialog" aria-modal="true">
                                        <form method="POST" action="{{ route('leads.convert',$record) }}" enctype="multipart/form-data" @click.outside="convertOpen=false" class="w-full max-w-lg overflow-hidden rounded-2xl bg-white text-left shadow-2xl">
                                            @csrf
                                            <header class="flex items-start justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="text-sm font-extrabold text-ink">Lengkapi data customer</h3><p class="mt-1 text-xs text-slate-500">{{ $record->brand_name }}</p></div><button type="button" @click="convertOpen=false" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500" aria-label="Tutup">×</button></header>
                                            <div class="space-y-4 p-5">
                                                <p class="rounded-xl bg-sky-50 px-4 py-3 text-xs leading-relaxed text-sky-700">Nama legal dan NPWP diperlukan agar identitas customer valid. Dokumen pendukung dapat ditambahkan bila tersedia.</p>
                                                <div><label class="label">Nama legal{{ $legalNameRequired ? ' *' : ' (opsional)' }}</label><input class="field" name="legal_name" value="{{ old('legal_name') }}" placeholder="Sesuai dokumen perusahaan" @required($legalNameRequired)></div>
                                                <div><label class="label">NPWP{{ $npwpRequired ? ' *' : ' (opsional)' }}</label><input class="field" name="npwp" value="{{ old('npwp') }}" placeholder="Masukkan nomor NPWP" @required($npwpRequired)></div>
                                                <div><label class="label">Dokumen pendukung</label><input class="field py-2.5" type="file" name="supporting_document" accept=".pdf,.jpg,.jpeg,.png,.webp"><p class="mt-1 text-[10px] text-slate-400">Opsional · PDF, JPG, PNG, atau WebP · maksimal 10 MB</p></div>
                                            </div>
                                            <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4"><button type="button" @click="convertOpen=false" class="btn-secondary">Batal</button><button class="btn-primary">Konversi menjadi customer</button></footer>
                                        </form>
                                    </div>
                                </template>
                                @endif
                            </div>
                        @else
                            <a href="{{ route('customers.show',$record) }}" class="inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-slate-600 shadow-sm hover:text-brand-700">Detail</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $view === 'prospects' ? 7 : 6 }}" class="p-14 text-center"><div class="text-sm font-bold text-slate-600">{{ $view==='prospects'?'Belum ada lead':'Customer tidak ditemukan' }}</div><div class="mt-1 text-xs text-slate-400">{{ $view==='prospects'?'Tambahkan lead pertama Anda.':'Coba ubah kata pencarian atau filter.' }}</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
<div class="mt-4">{{ $records->links() }}</div>
@endsection
