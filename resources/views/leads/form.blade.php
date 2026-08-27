@extends('layouts.app')
@section('title',$lead->exists?'Edit Lead':'Lead Baru')
@section('eyebrow','CRM / Customer')
@section('content')
<form method="POST" action="{{ $lead->exists ? route('leads.update',$lead) : route('leads.store') }}" data-duplicate-check data-duplicate-url="{{ route('customers.duplicate-check') }}" data-except-lead="{{ $lead->id }}">
    @csrf
    @if($lead->exists) @method('PUT') @endif
    <input type="hidden" name="duplicate_confirmed" value="{{ old('duplicate_confirmed', 0) }}" data-duplicate-confirmed>
<div class="grid gap-6 xl:grid-cols-[1fr_340px]">
    <div class="space-y-6">
        <section class="card p-6">
            <h3 class="section-title">Informasi lead</h3>
            <p class="mb-5 mt-1 text-xs text-slate-400">Data utama perusahaan dan kontak yang dapat dihubungi.</p>
            <div class="grid gap-5 md:grid-cols-2">
                <div><label class="label">Nama perusahaan *</label><input class="field" name="company_name" value="{{ old('company_name',$lead->company_name) }}" placeholder="Masukkan nama perusahaan" required></div>
                <div><label class="label">Nama brand</label><input class="field" name="brand_name" value="{{ old('brand_name',$lead->brand_name) }}" placeholder="Masukkan nama brand"></div>
                <div><label class="label">Nama PIC *</label><input class="field" name="contact_name" value="{{ old('contact_name',$lead->contact_name) }}" placeholder="Masukkan nama PIC" required></div>
                <div><label class="label">Nomor WhatsApp *</label><input class="field" name="phone" value="{{ old('phone',$lead->phone ?: $lead->whatsapp) }}" inputmode="tel" placeholder="08xxxxxxxxxx" required></div>
                <div><label class="label">Email</label><input type="email" class="field" name="email" value="{{ old('email',$lead->email) }}" placeholder="Masukkan alamat email"></div>
                <div><label class="label">Kota/Kabupaten</label><input class="field" name="city" value="{{ old('city',$lead->city) }}" placeholder="Masukkan kota atau kabupaten"></div>
                <div><label class="label">Area</label><select class="field" name="area_id"><option value="">Pilih area</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected(old('area_id',$lead->area_id)==$area->id)>{{ $area->name }}</option>@endforeach</select></div>
                <div>
                    <label class="label">Alamat lengkap</label>
                    <input class="field" name="address" value="{{ old('address',$lead->address) }}" placeholder="Jalan, nomor, kecamatan, dan kode pos">
                </div>
            </div>
                <div data-duplicate-warning class="mt-5 hidden rounded-xl border border-amber-200 bg-amber-50 p-4"></div>
                @error('duplicate')<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs font-semibold leading-relaxed text-amber-800">{{ $message }}</div>@enderror
            </section>

        <section class="card p-6">
            <h3 class="section-title">Kualifikasi</h3>
            <div class="mt-5 space-y-5">
                @php
                    $selectedBusinessType = old('business_type', $lead->business_type);
                @endphp
                @php
                    $interestItems = old('product_interests', $lead->interestItems());
                    $interestItems = array_values(array_filter($interestItems ?: [], fn ($item) => filled($item['product_name'] ?? null)));
                    $interestItemsJson = \Illuminate\Support\Js::from(array_values($interestItems))->toHtml();
                @endphp
                <div class="grid gap-4 lg:grid-cols-[minmax(220px,.9fr)_minmax(300px,1.35fr)_minmax(160px,.6fr)_110px_140px] lg:items-end" x-data="{
                    items: {!! $interestItemsJson !!},
                    draft: { product_name: '', estimated_need: '', estimated_need_unit: 'pcs' },
                    saveProduct() {
                        if (!String(this.draft.product_name || '').trim()) return;
                        const product = {
                            product_name: String(this.draft.product_name).trim(),
                            estimated_need: this.draft.estimated_need || '',
                            estimated_need_unit: this.draft.estimated_need_unit || 'pcs'
                        };
                        this.items.push(product);
                        this.draft = { product_name: '', estimated_need: '', estimated_need_unit: 'pcs' };
                    }
                }">
                    <div><label class="label">Jenis customer</label><select class="field" name="business_type"><option value="">Pilih jenis customer</option>@foreach($businessUnits->reject(fn ($unit) => strcasecmp($unit->name, 'Other') === 0) as $unit)<option value="{{ $unit->name }}" @selected($selectedBusinessType===$unit->name)>{{ $unit->name }}</option>@endforeach</select></div>
                    <div><label class="label">Produk yang diminati</label><input class="field" x-model="draft.product_name" placeholder="Masukkan nama produk" @keydown.enter.prevent="saveProduct()"></div>
                    <div><label class="label">Est. Qty/Bulan</label><input type="text" inputmode="numeric" data-quantity class="field" x-model="draft.estimated_need" placeholder="0" @keydown.enter.prevent="saveProduct()"></div>
                    <div><label class="label">UOM</label><select class="field" x-model="draft.estimated_need_unit" aria-label="UOM">@foreach(\App\Models\Opportunity::QUANTITY_UNITS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <button type="button" class="btn-secondary w-full whitespace-nowrap !px-3 text-xs disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-300" :disabled="!String(draft.product_name || '').trim()" @click="saveProduct()">+ Tambah produk</button>
                    <div x-show="items.length" x-cloak class="lg:col-span-5 mt-1 border-t border-slate-100 pt-4">
                        <div class="mb-3"><h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Daftar produk</h4><p class="mt-1 text-[11px] text-slate-400"><span x-text="items.length"></span> produk telah ditambahkan.</p></div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-2.5">
                                <input type="hidden" x-model="item.product_name" :name="`product_interests[${index}][product_name]`">
                                <input type="hidden" x-model="item.estimated_need" :name="`product_interests[${index}][estimated_need]`">
                                <input type="hidden" x-model="item.estimated_need_unit" :name="`product_interests[${index}][estimated_need_unit]`">
                                <div class="flex min-w-0 items-start justify-between gap-3">
                                    <div class="min-w-0"><div class="truncate text-xs font-extrabold text-ink" x-text="item.product_name"></div><div class="mt-0.5 text-[11px] text-slate-400"><span x-text="item.estimated_need ? Number(String(item.estimated_need).replace(/\D/g, '')).toLocaleString('id-ID') : '0'"></span> <span class="uppercase" x-text="item.estimated_need_unit"></span></div></div>
                                    <button type="button" class="inline-flex size-7 shrink-0 items-center justify-center rounded-md border border-rose-100 bg-white text-rose-500 hover:bg-rose-50" @click="items.splice(index, 1)" title="Hapus produk" aria-label="Hapus produk"><svg class="size-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h12M8 6V4h4v2m-6 0 1 11h6l1-11M9 9v5m2-5v5"/></svg></button>
                                </div>
                            </div>
                        </template>
                        </div>
                    </div>

                    </div>
                <div><label class="label">Catatan</label><textarea class="field w-full" rows="3" name="notes" placeholder="Tambahkan informasi kebutuhan atau hasil kualifikasi lead">{{ old('notes',$lead->notes) }}</textarea></div>
            </div>
        </section>
    </div>

    <aside class="space-y-6">
        <section class="card p-6">
            <h3 class="section-title">Assignment</h3>
            <div class="mt-5 space-y-4">
                @if($isSales)
                    <div><label class="label">Sales penanggung jawab</label><div class="field flex items-center bg-slate-50 font-semibold text-slate-700">{{ auth()->user()->name }}</div><input type="hidden" name="owner_id" value="{{ $lead->exists ? $lead->owner_id : auth()->id() }}"></div>
                @else
                    <div><label class="label">Sales penanggung jawab *</label><select class="field" name="owner_id" required><option value="">Pilih sales</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('owner_id',$lead->owner_id)==$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                @endif
                <div><label class="label">Source *</label><select class="field" name="source" required><option value="">Pilih source</option>@foreach(['website'=>'Website','whatsapp'=>'WhatsApp','referral'=>'Referral','sales_visit'=>'Sales Visit','event'=>'Event','ads'=>'Ads','social_media'=>'Social Media','marketplace'=>'Marketplace','database'=>'Database','telemarketing'=>'Telemarketing','walk_in'=>'Walk In','other'=>'Other'] as $value=>$label)<option value="{{ $value }}" @selected(old('source',$lead->source)===$value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="label">Status *</label><select class="field" name="status" required><option value="">Pilih status</option>@foreach(\App\Models\Lead::EDITABLE_STATUSES as $value=>$label)<option value="{{ $value }}" @selected(old('status',$lead->status)===$value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="label">Next follow-up</label><input type="datetime-local" class="field" name="next_follow_up_at" value="{{ old('next_follow_up_at',$lead->next_follow_up_at?->format('Y-m-d\TH:i')) }}"></div>
            </div>
        </section>
        <div class="flex gap-3"><a href="{{ route('customers.index',['view'=>'prospects']) }}" class="btn-secondary flex-1">Batal</a><button class="btn-primary flex-1">Simpan</button></div>
    </aside>
</div>
</form>
@endsection
