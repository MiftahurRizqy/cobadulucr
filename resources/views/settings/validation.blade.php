@extends('layouts.app')
@section('title','Validasi Data')
@section('eyebrow','Settings / Validasi')

@section('content')
<form method="POST" action="{{ route('settings.validation.update') }}" class="mx-auto max-w-3xl space-y-5">
    @csrf
    @method('PUT')
    <section class="card p-5">
        <label class="label">Terapkan untuk</label>
        <select class="field mt-2" onchange="window.location=this.value">
            <option value="{{ route('settings.validation.index') }}" @selected(!$selectedRoleId)>Semua role</option>
            @foreach($roles as $role)<option value="{{ route('settings.validation.index', ['role_id'=>$role->id]) }}" @selected($selectedRoleId === $role->id)>{{ $role->name }}</option>@endforeach
        </select>
        <p class="mt-2 text-xs text-slate-500">{{ $selectedRole ? 'Aturan khusus untuk role '.$selectedRole->name.'.' : 'Aturan bawaan yang berlaku untuk seluruh role.' }}</p>
    </section>
    <input type="hidden" name="role_id" value="{{ $selectedRoleId }}">
    <section class="card overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="section-title">Aturan kelengkapan data</h2>
            <p class="mt-1 text-xs text-slate-500">Tentukan informasi yang wajib diisi {{ $selectedRole ? 'oleh role '.$selectedRole->name : 'oleh seluruh pengguna' }}.</p>
        </header>
        <div class="divide-y divide-slate-100">
            @foreach([
                ['opportunity_product_photo_required', 'Foto produk opportunity', 'Wajibkan foto untuk setiap produk yang ditambahkan ke opportunity.', $productPhotoRequired],
                ['customer_legal_name_required', 'Nama legal customer', 'Wajibkan nama legal ketika lead dijadikan customer.', $legalNameRequired],
                ['customer_npwp_required', 'NPWP customer', 'Wajibkan NPWP ketika lead dijadikan customer.', $npwpRequired],
            ] as [$name,$label,$description,$enabled])
                <label class="flex cursor-pointer items-center justify-between gap-5 px-5 py-5 hover:bg-slate-50/70">
                    <span><span class="block text-sm font-extrabold text-ink">{{ $label }}</span><span class="mt-1 block text-xs text-slate-500">{{ $description }}</span></span>
                    <span class="relative shrink-0">
                        <input type="hidden" name="{{ $name }}" value="0">
                        <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked($enabled)>
                        <span class="block h-7 w-12 rounded-full bg-slate-200 transition peer-checked:bg-brand-600"></span>
                        <span class="absolute left-1 top-1 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                    </span>
                </label>
            @endforeach
        </div>
    </section>
    <div class="flex flex-wrap justify-end gap-2">
        @if($selectedRoleId && !$usesGlobalSettings)<button name="use_global" value="1" class="btn-secondary justify-center">Ikuti Semua role</button>@endif
        <button class="btn-primary min-w-36 justify-center">Simpan pengaturan</button>
    </div>
</form>
@endsection
