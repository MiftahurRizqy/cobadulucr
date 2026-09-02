@extends('layouts.app')
@section('title','Edit Customer')
@section('eyebrow','CRM / Customer')
@section('content')
@php
    $selected = collect(old('assigned_user_ids', $customer->assignedUsers->pluck('id')->all()))
        ->map(fn ($value) => (string) $value)->all();
@endphp

<form method="POST" action="{{ route('customers.update',$customer) }}" data-duplicate-check data-duplicate-url="{{ route('customers.duplicate-check') }}" data-except-customer="{{ $customer->id }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="duplicate_confirmed" value="{{ old('duplicate_confirmed', 0) }}" data-duplicate-confirmed>

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="space-y-6">
            <section class="card p-6">
                <h3 class="section-title">Informasi customer</h3>
                <p class="mb-5 mt-1 text-xs text-slate-400">Identitas bisnis dan informasi kontak utama.</p>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="label">Nama perusahaan *</label><input class="field" name="company_name" value="{{ old('company_name',$customer->company_name) }}" placeholder="Masukkan nama perusahaan" required></div>
                    <div><label class="label">Nama brand</label><input class="field" name="brand_name" value="{{ old('brand_name',$customer->brand_name) }}" placeholder="Masukkan nama brand"></div>
                    <div><label class="label">Nama legal</label><input class="field" name="legal_name" value="{{ old('legal_name',$customer->legal_name) }}" placeholder="Masukkan nama legal perusahaan"></div>
                    <div><label class="label">NPWP</label><input class="field" name="npwp" value="{{ old('npwp',$customer->npwp) }}" placeholder="Masukkan NPWP perusahaan"></div>
                    <div><label class="label">Nomor WhatsApp *</label><input class="field" name="phone" value="{{ old('phone',$customer->phone) }}" inputmode="tel" placeholder="08xxxxxxxxxx" required></div>
                    <div><label class="label">Email</label><input type="email" class="field" name="email" value="{{ old('email',$customer->email) }}" placeholder="Masukkan alamat email"></div>
                    <div><label class="label">Kota/Kabupaten</label><input class="field" name="city" value="{{ old('city',$customer->city) }}" placeholder="Masukkan kota atau kabupaten"></div>
                    <div><label class="label">Area</label><select class="field" name="area_id"><option value="">Pilih area</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected(old('area_id',$customer->area_id)==$area->id)>{{ $area->name }}</option>@endforeach</select></div>
                    <div style="grid-column: 1 / -1"><label class="label">Alamat lengkap</label><textarea class="field w-full" rows="3" name="address" placeholder="Nama jalan, nomor bangunan, kecamatan, dan kode pos">{{ old('address',$customer->address) }}</textarea></div>
                </div>
                <div data-duplicate-warning class="mt-5 hidden rounded-xl border border-amber-200 bg-amber-50 p-4"></div>
                @error('duplicate')<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs font-semibold leading-relaxed text-amber-800">{{ $message }}</div>@enderror
            </section>

            <section class="card p-6">
                <h3 class="section-title">Informasi transaksi</h3>
                <p class="mb-5 mt-1 text-xs text-slate-400">Ketentuan pembayaran dan perkiraan transaksi rutin customer.</p>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="label">Batas kredit</label><input type="number" min="0" class="field" name="credit_limit" value="{{ old('credit_limit',$customer->credit_limit) }}" placeholder="0"></div>
                    <div><label class="label">Tempo pembayaran (hari)</label><input type="number" min="0" class="field" name="payment_term_days" value="{{ old('payment_term_days',$customer->payment_term_days) }}" placeholder="0"></div>
                    <div><label class="label">Estimasi pembelian bulanan</label><input type="number" min="0" class="field" name="estimated_monthly_purchase" value="{{ old('estimated_monthly_purchase',$customer->estimated_monthly_purchase) }}" placeholder="0"></div>
                    <div><label class="label">Next follow-up</label><input type="datetime-local" class="field" name="next_follow_up_at" value="{{ old('next_follow_up_at',$customer->next_follow_up_at?->format('Y-m-d\TH:i')) }}"></div>
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="card p-6">
                <h3 class="section-title">Penanggung jawab</h3>
                <p class="mt-1 text-xs text-slate-400">Tentukan sales utama dan jenis customer.</p>
                <div class="mt-5 space-y-4">
                    @if($isSales)
                        <div><label class="label">Sales utama</label><div class="field flex items-center bg-slate-50 font-semibold text-slate-700">{{ auth()->user()->name }}</div><input type="hidden" name="sales_owner_id" value="{{ $customer->exists ? $customer->sales_owner_id : auth()->id() }}"></div>
                    @else
                        <div><label class="label">Sales/Telesales utama</label><select class="field" name="sales_owner_id"><option value="">Pilih Sales/Telesales</option>@foreach($users->filter(fn ($user) => $user->roles->whereIn('slug', ['sales', 'telesales'])->isNotEmpty()) as $user)<option value="{{ $user->id }}" @selected(old('sales_owner_id',$customer->sales_owner_id)==$user->id)>{{ $user->name }}</option>@endforeach</select><p class="mt-1 text-[10px] leading-relaxed text-slate-400">Jika diinput oleh CSA, kepemilikan dan KPI tetap tercatat untuk Sales/Telesales yang dipilih.</p></div>
                    @endif
                    <div><label class="label">Jenis customer</label><select class="field" name="business_type"><option value="">Pilih jenis customer</option>@foreach($businessUnits->reject(fn ($unit) => strcasecmp($unit->name, 'Other') === 0) as $unit)<option value="{{ $unit->name }}" @selected(old('business_type',$customer->business_type)===$unit->name)>{{ $unit->name }}</option>@endforeach</select></div>
                </div>
            </section>

            @unless($isSales)
            <section class="card p-6" x-data="{selected:@js($selected)}">
                <h3 class="section-title">Sales tambahan</h3>
                <p class="mt-1 text-xs text-slate-400">Pilih jika customer ditangani oleh lebih dari satu sales.</p>
                <div class="mt-4 max-h-52 space-y-1 overflow-y-auto">
                    @foreach($users->filter(fn ($user) => $user->roles->whereIn('slug', ['sales', 'telesales'])->isNotEmpty()) as $user)
                        <label class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-slate-50">
                            <input type="checkbox" name="assigned_user_ids[]" value="{{ $user->id }}" x-model="selected" class="size-4 accent-brand-600">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold">{{ $user->name }}</span>
                                <span class="block text-[10px] text-slate-400">{{ $user->user_id }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>
            @endunless

            <section class="card p-6">
                <div><label class="label">Status *</label><select class="field" name="status">@foreach(['pareto'=>'Cust Pareto','active'=>'Cust Aktif','inactive'=>'Cust Non Aktif','risky'=>'Cust Risky'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$customer->status?:'active')===$value)>{{ $label }}</option>@endforeach</select></div>
            </section>

            <div class="flex gap-3"><a href="{{ route('customers.index') }}" class="btn-secondary flex-1">Batal</a><button class="btn-primary flex-1">Simpan</button></div>
        </aside>
    </div>
</form>
@endsection
