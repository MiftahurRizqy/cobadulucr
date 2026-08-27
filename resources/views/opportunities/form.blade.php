@extends('layouts.app')
@section('title', 'Buat Opportunity')
@section('content')
@php
    $initialItems = old('items');
    if (!$initialItems) {
        $initialItems = $opportunity->getAttribute('initial_items') ?: [[
            'product_id' => $opportunity->product_id,
            'product_name' => $opportunity->product_name,
            'quantity' => $opportunity->estimated_quantity,
            'quantity_unit' => $opportunity->quantity_unit ?: 'pcs',
            'target_price' => $opportunity->target_price,
        ]];
    }
@endphp
<form method="POST" action="{{ route('opportunities.store') }}" enctype="multipart/form-data" x-data="{ selectedCollaborators: @js(array_map('strval', old('participant_ids', []))) }">
    @csrf
    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <div class="space-y-6">
            <section class="card p-6">
                <h3 class="section-title">Opportunity detail</h3>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div><label class="label">Customer *</label><select class="field" name="customer_id" required><option value="">Pilih customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id',$opportunity->customer_id)==$customer->id)>{{ $customer->company_name }}</option>@endforeach</select></div>
                    <div><label class="label">Pipeline *</label><select class="field" name="pipeline_id" required><option value="">Pilih pipeline</option>@foreach($pipelines as $pipeline)<option value="{{ $pipeline->id }}" @selected(old('pipeline_id')==$pipeline->id)>{{ $pipeline->name }}</option>@endforeach</select></div>
                    <div class="md:col-span-2"><label class="label">Judul opportunity *</label><input class="field" name="title" value="{{ old('title',$opportunity->title) }}" placeholder="Contoh: Supply produk Q4 untuk 12 outlet" required></div>
                    <div class="md:col-span-2"
                         x-data="{
                            items: @js($initialItems),
                            addItem() { this.items.push({product_name:'',quantity:1,quantity_unit:'pcs',target_price:'',photoPreview:null}) },
                            previewPhoto(item, event) { if (item.photoPreview) URL.revokeObjectURL(item.photoPreview); item.photoPreview = event.target.files[0] ? URL.createObjectURL(event.target.files[0]) : null },
                            moneyValue(value) { return Number(String(value ?? '').replace(/\D/g,'')) || 0 },
                            subtotal(item) { return (Number(item.quantity) || 0) * this.moneyValue(item.target_price) },
                            total() { return this.items.reduce((sum,item) => sum + this.subtotal(item), 0) },
                            rupiah(value) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(value || 0) }
                         }"
                         x-init="items = items.map(item => ({...item, photoPreview:null, quantity: Number(item.quantity) || 1, target_price: item.target_price ? new Intl.NumberFormat('id-ID').format(String(item.target_price).replace(/\D/g,'')) : ''}))">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div><h4 class="text-sm font-extrabold text-ink">Daftar produk</h4><p class="mt-1 text-xs text-slate-400">Tuliskan Est. Qty/Bulan dan target harga customer. Harga penawaran diisi saat tahap Quotation.</p></div>
                            <button type="button" class="btn-secondary" @click="addItem()">+ Tambah produk</button>
                        </div>
                        <div class="space-y-3">
                            <template x-for="(item,index) in items" :key="index">
                                <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                                    <div>
                                        <div><label class="mb-2 block whitespace-nowrap text-sm font-normal normal-case tracking-normal text-slate-600">Nama produk *</label><input class="field" x-model="item.product_name" :name="`items[${index}][product_name]`" placeholder="Ketik nama produk" required></div>
                                    </div>
                                    <div class="mt-3 grid gap-3 lg:grid-cols-[150px_120px_minmax(280px,1fr)]">
                                        <div><label class="mb-2 block whitespace-nowrap text-sm font-normal normal-case tracking-normal text-slate-600">Est. Qty/Bulan *</label><input type="number" min="1" class="field" x-model.number="item.quantity" :name="`items[${index}][quantity]`" required></div>
                                        <div><label class="mb-2 block whitespace-nowrap text-sm font-normal normal-case tracking-normal text-slate-600">Uom</label><select class="field" x-model="item.quantity_unit" :name="`items[${index}][quantity_unit]`">@foreach(\App\Models\Opportunity::QUANTITY_UNITS as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                        <div><label class="mb-2 block whitespace-nowrap text-sm font-normal normal-case tracking-normal text-slate-600">Target Harga</label><div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-normal text-slate-400">Rp</span><input type="text" inputmode="numeric" data-money class="field !pl-9" x-model="item.target_price" :name="`items[${index}][target_price]`" placeholder="0"></div><p class="mt-1 text-[10px] text-slate-400">Target harga per UOM yang diharapkan customer</p></div>
                                    </div>
                                    <div class="mt-3 border-t border-slate-200 pt-3"><label class="mb-2 block whitespace-nowrap text-sm font-normal normal-case tracking-normal text-slate-600">Foto produk *</label><div class="flex items-start gap-3"><img x-show="item.photoPreview" x-cloak :src="item.photoPreview" alt="Preview foto produk" class="size-20 shrink-0 rounded-lg border border-slate-200 object-cover"><input type="file" accept="image/jpeg,image/png,image/webp" class="field file:mr-3 file:border-0 file:bg-transparent file:text-xs file:font-bold file:text-brand-600" :name="`items[${index}][photo]`" @change="previewPhoto(item, $event)" required></div><p class="mt-1 text-[10px] text-slate-400">Preview akan tampil setelah foto dipilih. JPG, PNG, atau WebP maksimal 5 MB. Tanpa lokasi.</p></div>
                                    <div class="mt-3 flex justify-end" x-show="items.length > 1"><button type="button" class="text-xs font-bold text-rose-600" @click="items.splice(index,1)">Hapus produk</button></div>
                                </div>
                            </template>
                        </div>
                        <div class="mt-4 rounded-xl bg-brand-50 px-4 py-3"><div class="flex items-center justify-between gap-4"><span class="text-xs font-extrabold uppercase tracking-wide text-brand-700">Nilai target opportunity</span><strong class="text-lg text-brand-700" x-text="rupiah(total())"></strong></div><p class="mt-1 text-xs text-brand-600">Dihitung dari estimasi qty/bulan × target harga.</p></div>
                    </div>
                    <div><label class="label">Supplier yang digunakan saat ini</label><input class="field" name="current_supplier" value="{{ old('current_supplier') }}" placeholder="Kosongkan jika tidak diketahui"></div>
                    <div><label class="label">Kompetitor</label><input class="field" name="competitor" value="{{ old('competitor') }}" placeholder="Nama kompetitor jika ada"></div>
                </div>
            </section>
            <section class="card p-6">
                <h3 class="section-title">Next action</h3>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2"><label class="label">Next action</label><textarea class="field" rows="3" name="next_action">{{ old('next_action') }}</textarea></div>
                    <div><label class="label">Next follow-up</label><input type="datetime-local" class="field" name="next_follow_up_at" value="{{ old('next_follow_up_at') }}"></div>
                    <div><label class="label">Expected close</label><input type="date" class="field" name="expected_close_date" value="{{ old('expected_close_date') }}"></div>
                </div>
            </section>
        </div>
        <aside class="space-y-6">
            <section class="card p-6">
                <h3 class="section-title">Assignment</h3>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="label">Sales penanggung jawab</label>
                        @if($canAssignOwner)
                            <select class="field" name="owner_id">
                                <option value="">Ikuti sales pemegang customer</option>
                                @foreach($users as $user)<option value="{{ $user->id }}" @selected(old('owner_id')==$user->id)>{{ $user->name }}</option>@endforeach
                            </select>
                        @else
                            <input type="hidden" name="owner_id" value="{{ auth()->id() }}">
                            <div class="field bg-slate-50 text-slate-600">{{ auth()->user()->name }} <span class="text-slate-400">(otomatis)</span></div>
                        @endif
                    </div>
                    <div>
                        <label class="label">Kolaborasi</label>
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button type="button" class="field flex items-center justify-between gap-3 text-left" @click="open = !open">
                                <span class="min-w-0 flex-1 truncate" x-text="selectedCollaborators.length ? selectedCollaborators.length + ' orang dipilih' : 'Pilih rekan (opsional)'"></span>
                                <svg class="size-4 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div x-show="open" x-cloak class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                                @forelse($collaborationUsers as $collaborator)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 hover:bg-slate-50">
                                        <input type="checkbox" name="participant_ids[]" value="{{ $collaborator->id }}" x-model="selectedCollaborators" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-[9px] font-black text-brand-700">{{ collect(explode(' ', $collaborator->name))->take(2)->map(fn($word) => mb_substr($word, 0, 1))->join('') }}</span>
                                        <span class="min-w-0 flex-1"><span class="block truncate text-[11px] font-bold text-slate-700">{{ $collaborator->name }}</span><span class="block truncate text-[9px] text-slate-400">{{ $collaborator->employee_id ?: ucfirst($collaborator->user_type) }}</span></span>
                                    </label>
                                @empty
                                    <div class="p-4 text-center text-[10px] text-slate-400">Belum ada rekan yang dapat dipilih.</div>
                                @endforelse
                            </div>
                        </div>
                        <p class="mt-2 text-[9px] leading-relaxed text-slate-400">Rekan yang diundang akan menerima notifikasi dan mendapat akses ke opportunity ini.</p>
                    </div>
                    <div><label class="label">Priority *</label><select class="field" name="priority">@foreach(['low','medium','high'] as $priority)<option value="{{ $priority }}" @selected(old('priority','medium')===$priority)>{{ ucfirst($priority) }}</option>@endforeach</select></div>
                    <div><label class="label">Sumber lead</label><select class="field" name="lead_source"><option value="">Pilih sumber lead</option>@foreach(['website'=>'Website','whatsapp'=>'WhatsApp','referral'=>'Referral','sales_visit'=>'Sales Visit','event'=>'Event','ads'=>'Ads','social_media'=>'Social Media','marketplace'=>'Marketplace','database'=>'Database','telemarketing'=>'Telemarketing','walk_in'=>'Walk In','other'=>'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('lead_source') === $value)>{{ $label }}</option>@endforeach</select></div>
                </div>
            </section>
            <div class="flex gap-3"><a href="{{ route('opportunities.index') }}" class="btn-secondary flex-1">Batal</a><button class="btn-primary flex-1">Simpan</button></div>
        </aside>
    </div>
</form>
@endsection
