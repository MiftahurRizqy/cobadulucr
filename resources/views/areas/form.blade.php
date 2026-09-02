@extends('layouts.app')
@section('title',$area->exists ? 'Edit Area' : 'Tambah Area')
@section('eyebrow','Administrasi · Area')
@section('content')
<form method="POST" action="{{ $area->exists ? route('areas.update',$area) : route('areas.store') }}" class="mx-auto max-w-3xl">
    @csrf
    @if($area->exists) @method('PUT') @endif
    <section class="card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4"><h3 class="section-title">Informasi area</h3><p class="mt-1 text-[11px] text-slate-400">Kelola wilayah customer dan lead.</p></div>
        <div class="grid gap-5 p-5 md:grid-cols-2">
            <div><label class="label">Kode area *</label><input class="field uppercase" name="code" value="{{ old('code',$area->code) }}" placeholder="Contoh: JKT" maxlength="20" required><p class="mt-1.5 text-[9px] text-slate-400">Kode harus unik dan singkat.</p></div>
            <div><label class="label">Nama area *</label><input class="field" name="name" value="{{ old('name',$area->name) }}" placeholder="Contoh: Jabodetabek" required></div>
            <div><label class="label">Status *</label><select class="field" name="is_active"><option value="1" @selected((string)old('is_active',$area->is_active ?? true)==='1')>Aktif</option><option value="0" @selected((string)old('is_active',$area->is_active ?? true)==='0')>Nonaktif</option></select><p class="mt-1.5 text-[9px] text-slate-400">Area nonaktif tetap mempertahankan data lama.</p></div>
        </div>
    </section>
    <div class="mt-5 flex justify-end gap-3"><a href="{{ route('areas.index') }}" class="btn-secondary">Batal</a><button class="btn-primary">{{ $area->exists ? 'Simpan perubahan' : 'Tambah area' }}</button></div>
</form>
@endsection
