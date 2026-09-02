@extends('layouts.app')
@section('title','Konfigurasi Operasional')
@section('eyebrow','Settings / Operasional')
@section('content')
<form method="POST" action="{{ route('settings.operational.update') }}" class="mx-auto max-w-3xl space-y-5">
    @csrf @method('PUT')
    <section class="card overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4"><h2 class="section-title">Fitur sesuai proses bisnis</h2><p class="mt-1 text-xs text-slate-500">Aktifkan hanya proses dan metrik yang digunakan perusahaan ini.</p></header>
        <div class="divide-y divide-slate-100">
            @foreach([
                ['custom_progress_enabled','Custom progress','Pantau pekerjaan produk Custom lintas pipeline. Pipeline yang ditandai sebagai alur Custom menentukan tahapan progresnya.', $customProgressEnabled],
                ['product_market_segment_enabled','Klasifikasi industri','Tampilkan pilihan Drink, Food, dan Industri pada produk. Drink dan Food dapat digunakan untuk laporan volume.', $marketSegmentEnabled],
                ['operational_kpi_enabled','KPI operasional','Gunakan target operasional seperti NOO, Custom, Drink, dan Food pada KPI penjualan.', $operationalKpiEnabled],
            ] as [$name,$label,$description,$enabled])
                <label class="flex cursor-pointer items-center justify-between gap-5 px-5 py-5 hover:bg-slate-50/70"><span><span class="block text-sm font-extrabold text-ink">{{ $label }}</span><span class="mt-1 block max-w-xl text-xs leading-relaxed text-slate-500">{{ $description }}</span></span><span class="relative shrink-0"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked($enabled)><span class="block h-7 w-12 rounded-full bg-slate-200 transition peer-checked:bg-brand-600"></span><span class="absolute left-1 top-1 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span></span></label>
            @endforeach
        </div>
    </section>
    <section class="card p-5"><h2 class="section-title">Jenis produk</h2><p class="mt-1 text-xs text-slate-500">Nonaktifkan jika perusahaan tidak membedakan produk standar dan produk khusus.</p><div class="mt-5 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"><div><label class="label">Label jenis pertama</label><input class="field mt-1" name="product_type_regular_label" value="{{ $productTypeConfig['regular_label'] ?? 'Reguler' }}"></div><div><label class="label">Label jenis kedua</label><input class="field mt-1" name="product_type_custom_label" value="{{ $productTypeConfig['custom_label'] ?? 'Custom' }}"></div><label class="flex h-11 items-center gap-2 text-xs font-bold text-slate-600"><input type="hidden" name="product_type_enabled" value="0"><input type="checkbox" name="product_type_enabled" value="1" class="size-4 accent-brand-600" @checked($productTypeConfig['enabled'] ?? true)> Gunakan jenis produk</label></div></section>
    <section class="card p-5"><h2 class="section-title">Klasifikasi produk</h2><p class="mt-1 text-xs text-slate-500">Ubah nama kategori sesuai istilah yang digunakan perusahaan.</p><div class="mt-5 grid gap-4 sm:grid-cols-3"><div><label class="label">Kategori pertama</label><input class="field mt-1" name="market_drink_label" value="{{ $marketSegmentConfig['drink_label'] ?? 'Drink' }}"></div><div><label class="label">Kategori kedua</label><input class="field mt-1" name="market_food_label" value="{{ $marketSegmentConfig['food_label'] ?? 'Food' }}"></div><div><label class="label">Kategori ketiga</label><input class="field mt-1" name="market_industry_label" value="{{ $marketSegmentConfig['industry_label'] ?? 'Industri' }}"></div></div></section>
    <div class="flex justify-end"><button class="btn-primary min-w-44 justify-center">Simpan pengaturan</button></div>
</form>
@endsection
