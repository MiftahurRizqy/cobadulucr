@extends('layouts.app')
@section('title','Jenis Customer')
@section('eyebrow','Administrasi / Jenis Customer')
@section('content')
<div>
    <section class="card overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="section-title">Daftar jenis customer</h3>
                <p class="mt-1 text-xs text-slate-400">Kelola pilihan yang digunakan pada lead, customer, pipeline, dan laporan.</p>
            </div>
            <form method="POST" action="{{ route('settings.customer-types.store') }}" class="flex w-full gap-2 lg:max-w-md">
                @csrf
                <input class="field !h-10" name="name" value="{{ old('name') }}" placeholder="Nama jenis customer baru" required maxlength="120">
                <button class="btn-primary !h-10 shrink-0 !px-4">+ Tambah</button>
            </form>
        </div>

        @error('name')<div class="mx-5 mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700">{{ $message }}</div>@enderror

        <div class="grid gap-3 p-5 lg:grid-cols-2">
            @forelse($customerTypes as $customerType)
                <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-3">
                    <div class="flex items-center gap-2">
                        <form id="customer-type-{{ $customerType->id }}" method="POST" action="{{ route('settings.customer-types.update', $customerType) }}" class="min-w-0 flex-1">
                            @csrf
                            @method('PUT')
                            <input class="field !h-9 !rounded-lg !px-3 text-sm" name="name" value="{{ $customerType->name }}" required maxlength="120" aria-label="Nama jenis customer">
                        </form>
                        <button form="customer-type-{{ $customerType->id }}" class="inline-grid size-9 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-brand-200 hover:text-brand-600" title="Simpan perubahan" aria-label="Simpan perubahan">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 3h10l2 2v12H4z"/><path d="M7 3v5h6V3M7 17v-5h6v5"/></svg>
                        </button>
                        <form method="POST" action="{{ route('settings.customer-types.toggle', $customerType) }}">
                            @csrf
                            @method('PATCH')
                            <button class="inline-grid size-9 place-items-center rounded-lg border bg-white shadow-sm transition {{ $customerType->is_active ? 'border-amber-200 text-amber-600 hover:bg-amber-50' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50' }}" title="{{ $customerType->is_active ? 'Nonaktifkan' : 'Aktifkan' }}" aria-label="{{ $customerType->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                @if($customerType->is_active)
                                    <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10" cy="10" r="7"/><path d="M7.5 7.5l5 5"/></svg>
                                @else
                                    <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10" cy="10" r="7"/><path d="m7 10 2 2 4-4"/></svg>
                                @endif
                            </button>
                        </form>
                        @if($customerType->usage_count === 0)
                            <form method="POST" action="{{ route('settings.customer-types.destroy', $customerType) }}" onsubmit="return confirm('Hapus jenis customer ini secara permanen?')">
                                @csrf
                                @method('DELETE')
                                <button class="inline-grid size-9 place-items-center rounded-lg border border-rose-200 bg-white text-rose-500 shadow-sm transition hover:bg-rose-50" title="Hapus" aria-label="Hapus">
                                    <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h12M8 6V4h4v2m-6 0 1 11h6l1-11M9 9v5m2-5v5"/></svg>
                                </button>
                            </form>
                        @endif
                    </div>
                    <div class="mt-2 flex items-center gap-2 px-1 text-[11px] text-slate-400">
                        <span class="inline-flex rounded-full px-2 py-0.5 font-extrabold {{ $customerType->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $customerType->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        <span><b class="font-extrabold text-slate-600">{{ $customerType->usage_count }}</b> penggunaan</span>
                    </div>
                </div>
            @empty
                <div class="empty-state">Belum ada jenis customer.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
