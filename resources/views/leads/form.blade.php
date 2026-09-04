@extends('layouts.app')
@section('title',$lead->exists?'Edit Lead':'Lead Baru')
@section('eyebrow','CRM / Customer')
@section('content')
@php
    $selectedCollaborators = collect(old('collaborator_ids', $lead->exists ? $lead->collaborators->pluck('id')->all() : []))
        ->map(fn ($id) => (string) $id);
@endphp
<form method="POST" action="{{ $lead->exists ? route('leads.update',$lead) : route('leads.store') }}" data-duplicate-check data-duplicate-url="{{ route('customers.duplicate-check') }}" data-except-lead="{{ $lead->id }}">
    @csrf
    @if($lead->exists) @method('PUT') @endif
    <input type="hidden" name="duplicate_confirmed" value="{{ old('duplicate_confirmed', 0) }}" data-duplicate-confirmed>
<div class="grid gap-6 xl:grid-cols-[1fr_340px]">
    <div class="space-y-6">
        <section class="card p-6">
            <h3 class="section-title">Informasi lead</h3>
            <p class="mb-5 mt-1 text-xs text-slate-400">Data utama brand dan kontak yang dapat dihubungi.</p>
            <div class="grid gap-5 md:grid-cols-2">
                <div><label class="label">Nama brand *</label><input class="field" name="brand_name" value="{{ old('brand_name',$lead->brand_name) }}" placeholder="Masukkan nama brand" required></div>
                <div><label class="label">Nama perusahaan</label><input class="field" name="company_name" value="{{ old('company_name',$lead->company_name) }}" placeholder="Masukkan nama perusahaan (opsional)"></div>
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
                    @php($ownerOptions = $users->map(fn ($user) => ['id' => (string) $user->id, 'name' => $user->name, 'role' => $user->roleNames()])->values())
                    <div class="relative" x-data="{ open: false, search: '', selected: @js((string) old('owner_id', $lead->owner_id ?? '')), options: @js($ownerOptions), get chosen() { return this.options.find(item => item.id === this.selected); }, get matches() { const q = this.search.toLowerCase(); return this.options.filter(item => !q || item.name.toLowerCase().includes(q) || item.role.toLowerCase().includes(q)); }, choose(id) { this.selected = id; this.open = false; this.search = ''; window.dispatchEvent(new CustomEvent('lead-owner-selected', { detail: id })); } }" @keydown.escape.window="open=false">
                        <label class="label">Sales/Telesales penanggung jawab *</label>
                        <input type="hidden" name="owner_id" :value="selected">
                        <button type="button" class="field flex items-center justify-between gap-3 text-left" @click="open=!open">
                            <span class="min-w-0 truncate text-sm" :class="chosen ? 'font-semibold text-slate-700' : 'text-slate-400'" x-text="chosen ? chosen.name : 'Pilih Sales/Telesales'"></span>
                            <svg class="size-4 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 7.5 5 5 5-5"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open=false" class="absolute right-0 top-[66px] z-50 w-full min-w-[280px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                            <div class="border-b border-slate-100 p-2.5"><input type="search" class="field h-9 text-xs" x-model="search" placeholder="Cari Sales/Telesales..."></div>
                            <div class="max-h-52 overflow-y-auto p-1.5">
                                <template x-for="item in matches" :key="item.id"><button type="button" class="flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left hover:bg-slate-50" @click="choose(item.id)"><span class="min-w-0"><span class="block truncate text-xs font-semibold text-slate-700" x-text="item.name"></span><span class="block truncate text-[9px] text-slate-400" x-text="item.role"></span></span><svg x-show="selected === item.id" class="size-4 shrink-0 text-brand-600" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m4 10 4 4 8-8"/></svg></button></template>
                                <div x-show="!matches.length" class="px-3 py-6 text-center text-[10px] text-slate-400">Sales/Telesales tidak ditemukan.</div>
                            </div>
                        </div>
                    </div>
                @endif
                @if($canInvite)
                @php($collaborationOptions = $users->map(fn ($user) => ['id' => (string) $user->id, 'name' => $user->name, 'role' => $user->roleNames(), 'canCollaborate' => $user->roles->contains(fn ($role) => in_array($role->slug, ['sales', 'telesales'], true))])->values())
                <div class="relative" x-data="{ open: false, search: '', selected: @js($selectedCollaborators->values()), ownerId: @js((string) ($isSales ? auth()->id() : old('owner_id', $lead->owner_id ?? ''))), options: @js($collaborationOptions), get matches() { const q = this.search.toLowerCase(); return this.options.filter(item => item.canCollaborate && item.id !== this.ownerId && (!q || item.name.toLowerCase().includes(q) || item.role.toLowerCase().includes(q))); } }" @lead-owner-selected.window="ownerId = $event.detail; selected = selected.filter(id => id !== ownerId)" @keydown.escape.window="open=false">
                    <label class="label">Kolaborasi</label>
                    <button type="button" class="field flex items-center justify-between gap-3 text-left" @click="open=!open">
                        <span class="min-w-0 truncate text-sm" :class="selected.length ? 'font-semibold text-slate-700' : 'text-slate-400'" x-text="selected.length ? selected.length + ' rekan dipilih' : 'Pilih rekan (opsional)'"></span>
                        <svg class="size-4 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 7.5 5 5 5-5"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open=false" class="absolute right-0 top-[66px] z-50 w-full min-w-[280px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
                        <div class="border-b border-slate-100 p-2.5"><input type="search" class="field h-9 text-xs" x-model="search" placeholder="Cari Sales/Telesales..."></div>
                        <div class="max-h-52 overflow-y-auto p-1.5">
                            <template x-for="item in matches" :key="item.id"><label class="flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 hover:bg-slate-50"><input type="checkbox" name="collaborator_ids[]" :value="item.id" x-model="selected" class="size-4 accent-brand-600"><span class="min-w-0"><span class="block truncate text-xs font-semibold text-slate-700" x-text="item.name"></span><span class="block truncate text-[9px] text-slate-400" x-text="item.role"></span></span></label></template>
                            <div x-show="!matches.length" class="px-3 py-6 text-center text-[10px] text-slate-400">Rekan tidak ditemukan.</div>
                        </div>
                    </div>
                </div>
                @endif
                <div x-data="{ source: @js(old('source',$lead->source)) }"><label class="label">Source *</label><select class="field" name="source" x-model="source" required><option value="">Pilih source</option>@foreach($sourceOptions as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><div x-show="source === 'other'" x-cloak class="mt-2"><input class="field" name="source_custom" value="{{ old('source_custom') }}" placeholder="Tulis source lain, contoh: Instagram" :required="source === 'other'"><p class="mt-1 text-[10px] text-slate-400">Nama yang Anda isi langsung tersimpan dan muncul pada dropdown source berikutnya.</p></div></div>
                <div><label class="label">Status *</label><select class="field" name="status" required><option value="">Pilih status</option>@foreach(\App\Models\Lead::EDITABLE_STATUSES as $value=>$label)<option value="{{ $value }}" @selected(old('status',$lead->status)===$value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="label">Next follow-up</label><input type="datetime-local" class="field" name="next_follow_up_at" value="{{ old('next_follow_up_at',$lead->next_follow_up_at?->format('Y-m-d\TH:i')) }}"></div>
            </div>
        </section>
        <div class="flex gap-3">@if($lead->exists)<a href="{{ route('activities.create',['lead'=>$lead->id]) }}" class="btn-secondary flex-1">+ Catat aktivitas</a>@else<a href="{{ route('customers.index',['view'=>'prospects']) }}" class="btn-secondary flex-1">Batal</a>@endif<button class="btn-primary flex-1">Simpan</button></div>
    </aside>
</div>
</form>
@endsection
