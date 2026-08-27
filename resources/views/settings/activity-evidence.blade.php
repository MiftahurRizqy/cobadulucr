@extends('layouts.app')
@section('title','Kebijakan Bukti Aktivitas')
@section('eyebrow','Administrasi · Pengaturan aktivitas')
@section('content')
<form method="POST" action="{{ route('settings.activity-evidence.update') }}" class="space-y-5">
    @csrf
    @method('PUT')
    <section class="card overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4"><h3 class="section-title">Atur berdasarkan divisi</h3><p class="mt-1 max-w-2xl text-[11px] leading-relaxed text-slate-400">Divisi yang diaktifkan wajib menyertakan minimal satu gambar pada setiap aktivitas. PDF boleh ditambahkan sebagai dokumen pendukung.</p></div>
        <div class="divide-y divide-slate-100">
            @forelse($departments as $department)
            <label class="flex cursor-pointer items-center gap-4 px-5 py-4 transition hover:bg-slate-50">
                <input type="checkbox" name="required_department_ids[]" value="{{ $department->id }}" class="peer sr-only" @checked($department->activity_evidence_required)>
                <span class="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition peer-checked:bg-brand-600 after:absolute after:left-1 after:top-1 after:size-4 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:after:translate-x-5"></span>
                <span class="min-w-0 flex-1"><span class="block text-xs font-extrabold text-ink">{{ $department->name }}</span><span class="mt-1 block text-[10px] text-slate-400">{{ $department->businessUnit?->name ?? 'Semua unit bisnis' }} · {{ $department->users_count }} pengguna</span></span>
                <span class="hidden text-[10px] font-bold text-slate-400 peer-checked:text-brand-600 sm:block">Bukti gambar wajib</span>
            </label>
            @empty<div class="empty-state">Belum ada divisi.</div>@endforelse
        </div>
    </section>
    <div class="flex justify-end"><button class="btn-primary">Simpan kebijakan</button></div>
</form>
@endsection
