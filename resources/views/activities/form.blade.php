@extends('layouts.app')
@section('title','Catat Aktivitas')
@section('eyebrow','CRM · Pelacakan aktivitas')
@section('content')
@php($oldSpecialPriceItems = old('special_price_items', []))
<form
    method="POST"
    enctype="multipart/form-data"
    action="{{ route('activities.store') }}"
    novalidate
    x-data="{
        type: @js(old('type', $activity->type ?: 'intro_contact')),
        decisionTypes: @js(\App\Models\Activity::DECISION_TYPES),
        approverIds: @js($collaborationUsers->where('is_active', true)->where('is_approver', true)->pluck('id')->map(fn($id)=>(string)$id)->values()),
        typePickerOpen: false,
        selectedCustomer: @js((string) old('customer_id', $activity->customer_id)),
        selectedOpportunity: @js((string) old('opportunity_id', $activity->opportunity_id)),
        opportunityOptions: @js($opportunities->map(fn($opportunity) => [
            'id' => (string) $opportunity->id,
            'customer_id' => (string) $opportunity->customer_id,
            'title' => $opportunity->title,
            'stage' => $opportunity->stage?->name,
            'items' => $opportunity->items->map(fn($item) => [
                'id' => (string) $item->id,
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'unit' => strtoupper($item->quantity_unit),
                'target_price' => (float) ($item->target_price ?? 0),
                'unit_price' => (float) ($item->unit_price ?? 0),
                'selected' => !empty($oldSpecialPriceItems[$item->id]['selected']),
                'requested_price' => $oldSpecialPriceItems[$item->id]['requested_price'] ?? '',
                'reason' => $oldSpecialPriceItems[$item->id]['reason'] ?? '',
            ])->values(),
        ])->values()),
        activityDetail: @js((string) old('detail', $activity->detail)),
        activityResult: @js((string) old('result', $activity->result)),
        customerCreditLimits: @js($customers->mapWithKeys(fn($customer) => [(string) $customer->id => (float) ($customer->credit_limit ?? 0)])),
        selectedFollowUp: @js((string) old('completes_follow_up_id', $completesFollowUp?->id)),
        pendingFollowUps: [],
        followUpsLoading: false,
        pendingFollowUpsUrl: @js(route('activities.follow-ups.pending')),
        baseEvidenceRequired: @js($evidenceRequired),
        requiresEvidence() {
            return this.baseEvidenceRequired && !this.decisionTypes.includes(this.type);
        },
        files: [],
        dragging: false,
        fileMetadata: {},
        pendingLocation: null,
        cameraStatus: 'idle',
        selectedCollaborators: @js(array_map('strval', old('participant_ids', $activity->participants ?? []))),
        creditTick: 0,
        approvalNumber(key) {
            this.creditTick;
            const input = this.$root.querySelector(`[name='approval_details[${key}]']:not(:disabled)`);
            return Number(String(input?.value || '').replace(/\D/g, '')) || 0;
        },
        rupiah(value) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value || 0);
        },
        selectedCustomerCreditLimit() {
            return Number(this.customerCreditLimits[String(this.selectedCustomer)] || 0);
        },
        remainingCredit() {
            return this.approvalNumber('current_limit') - this.approvalNumber('outstanding_receivables');
        },
        creditOverLimit() {
            return Math.max(0, this.approvalNumber('new_order_value') - Math.max(0, this.remainingCredit()));
        },
        additionalTermDays() {
            return Math.max(0, this.approvalNumber('requested_days') - this.approvalNumber('current_days'));
        },
        hasSelectedApprover() {
            return this.selectedCollaborators.some(id => this.approverIds.includes(String(id)));
        },
        init() {
            if (this.decisionTypes.includes(this.type)) {
                this.selectedCollaborators = this.selectedCollaborators.filter(id => this.approverIds.includes(String(id)));
            }
            this.$watch('type', value => {
                if (this.decisionTypes.includes(value)) {
                    this.selectedCollaborators = this.selectedCollaborators.filter(id => this.approverIds.includes(String(id)));
                }
            });
            this.$watch('selectedCustomer', () => {
                if (this.selectedOpportunity && !this.filteredOpportunities().some(item => item.id === String(this.selectedOpportunity))) {
                    this.selectedOpportunity = '';
                }
                this.loadPendingFollowUps();
            });
            if (this.selectedCustomer) this.loadPendingFollowUps();
        },
        filteredOpportunities() {
            return this.opportunityOptions.filter(item => item.customer_id === String(this.selectedCustomer));
        },
        opportunityLabel(item) {
            return item.title + (item.stage ? ' — ' + item.stage : '');
        },
        selectedOpportunityItems() {
            return this.opportunityOptions.find(item => item.id === String(this.selectedOpportunity))?.items || [];
        },
        async loadPendingFollowUps() {
            if (!this.selectedCustomer) {
                this.pendingFollowUps = [];
                return;
            }
            this.followUpsLoading = true;
            try {
                const response = await fetch(this.pendingFollowUpsUrl + '?customer_id=' + encodeURIComponent(this.selectedCustomer), { headers: { Accept: 'application/json' } });
                this.pendingFollowUps = response.ok ? await response.json() : [];
                if (this.selectedFollowUp && !this.pendingFollowUps.some(item => String(item.id) === String(this.selectedFollowUp))) this.selectedFollowUp = '';
            } finally {
                this.followUpsLoading = false;
            }
        },
        fileKey(file) {
            return file.name + ':' + file.size + ':' + file.lastModified;
        },
        setFiles(fileList, source = 'upload', location = null) {
            const transfer = new DataTransfer();
            this.fileMetadata = {};
            Array.from(fileList).slice(0, 5).forEach(file => {
                transfer.items.add(file);
                this.fileMetadata[this.fileKey(file)] = { source, ...(location || {}) };
            });
            this.$refs.evidence.files = transfer.files;
            this.refreshFiles();
        },
        appendFiles(fileList, source = 'upload', location = null) {
            const transfer = new DataTransfer();
            Array.from(this.$refs.evidence.files).forEach(file => transfer.items.add(file));
            Array.from(fileList).forEach(file => {
                if (transfer.files.length >= 5) return;
                transfer.items.add(file);
                this.fileMetadata[this.fileKey(file)] = { source, ...(location || {}) };
            });
            this.$refs.evidence.files = transfer.files;
            this.refreshFiles();
        },
        refreshFiles() {
            this.files.forEach(file => file.url && URL.revokeObjectURL(file.url));
            const selectedFiles = Array.from(this.$refs.evidence.files);
            this.files = selectedFiles.map(file => {
                const metadata = this.fileMetadata[this.fileKey(file)] || { source: 'upload' };
                this.fileMetadata[this.fileKey(file)] = metadata;
                const heic = this.isHeic(file);
                return {
                    key: this.fileKey(file),
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    lastModified: file.lastModified,
                    url: !heic && file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                    previewLoading: heic,
                    ...metadata,
                };
            });
            selectedFiles.forEach((file, index) => {
                if (this.isHeic(file)) this.loadHeicPreview(file, index);
            });
        },
        async loadHeicPreview(file, index) {
            const key = this.fileKey(file);
            try {
                const previewUrl = await window.createHeicPreview(file);
                if (this.files[index]?.key !== key) {
                    URL.revokeObjectURL(previewUrl);
                    return;
                }
                this.files[index].url = previewUrl;
            } catch (_) {
                if (this.files[index]?.key === key) this.files[index].previewFailed = true;
            } finally {
                if (this.files[index]?.key === key) this.files[index].previewLoading = false;
            }
        },
        removeFile(index) {
            const transfer = new DataTransfer();
            Array.from(this.$refs.evidence.files).forEach((file, position) => {
                if (position !== index) transfer.items.add(file);
            });
            this.$refs.evidence.files = transfer.files;
            this.refreshFiles();
        },
        fileSize(bytes) {
            return bytes < 1048576 ? Math.ceil(bytes / 1024) + ' KB' : (bytes / 1048576).toFixed(1) + ' MB';
        },
        hasImage() {
            return this.files.some(file => file.type.startsWith('image/') || this.isHeic(file));
        },
        isHeic(file) {
            return ['image/heic', 'image/heif'].includes((file.type || '').toLowerCase())
                || /\.(heic|heif)$/i.test(file.name || '');
        },
        openCamera() {
            this.cameraStatus = 'locating';
            if (!navigator.geolocation) {
                this.cameraStatus = 'unsupported';
                this.$refs.camera.click();
                return;
            }
            navigator.geolocation.getCurrentPosition(position => {
                this.pendingLocation = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                    locationRecordedAt: Date.now(),
                };
                this.cameraStatus = 'ready';
                this.$refs.camera.click();
            }, () => {
                this.pendingLocation = null;
                this.cameraStatus = 'denied';
                this.$refs.camera.click();
            }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
        },
        handleCameraFiles(fileList) {
            if (fileList.length) this.appendFiles(fileList, 'camera', this.pendingLocation);
            this.$refs.camera.value = '';
            this.pendingLocation = null;
        },
    }"
>
    @csrf
    @if($completesFollowUp)
        <div class="mb-5 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div><div class="text-[10px] font-black uppercase tracking-wider text-amber-700">Mengerjakan follow-up</div><div class="mt-1 text-xs font-semibold text-slate-700">{{ $completesFollowUp->summary }}</div><div class="mt-1 text-[10px] text-slate-500">Jadwal {{ $completesFollowUp->next_follow_up_at?->translatedFormat('d M Y, H:i') }}. Setelah aktivitas ini disimpan, follow-up lama otomatis ditandai selesai.</div></div>
            <a href="{{ route('customers.show', $completesFollowUp->customer_id) }}" class="btn-secondary shrink-0">Batal</a>
        </div>
    @endif
    <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
        <div class="space-y-5">
            <section class="card p-5 md:p-6">
                <div><h3 class="section-title">Aktivitas & customer</h3><p class="mt-1 text-[11px] text-slate-400">Pilih proses CRM lalu lengkapi hasil aktivitasnya.</p></div>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div><label class="label">Customer *</label><select class="field disabled:bg-slate-100 disabled:text-slate-500" name="customer_id" x-model="selectedCustomer" required><option value="">Pilih customer</option>@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->company_name }}</option>@endforeach</select></div>
                    <div>
                        <label class="label">Opportunity</label>
                        <select x-ref="opportunitySelect" class="field disabled:bg-slate-100 disabled:text-slate-500" name="opportunity_id" x-model="selectedOpportunity" :disabled="!selectedCustomer">
                            <option value="" x-text="selectedCustomer ? 'Tanpa opportunity' : 'Pilih customer terlebih dahulu'"></option>
                            <template x-for="item in filteredOpportunities()" :key="item.id">
                                <option :value="item.id" :selected="String(selectedOpportunity) === String(item.id)" x-text="opportunityLabel(item)"></option>
                            </template>
                        </select>
                        <p x-show="selectedCustomer && !filteredOpportunities().length" x-cloak class="mt-2 text-[10px] text-slate-400">Customer ini belum memiliki opportunity. Aktivitas tetap dapat disimpan tanpa opportunity.</p>
                        @error('opportunity_id')<p class="mt-2 text-[10px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="relative" @keydown.escape.window="typePickerOpen=false">
                        <label class="label">Jenis aktivitas *</label>
                        <input type="hidden" name="type" :value="type">
                        <button type="button" class="field flex h-[42px] items-center justify-between gap-3 py-0 text-left disabled:bg-slate-100 disabled:text-slate-500" @click="typePickerOpen=!typePickerOpen">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="grid size-7 shrink-0 place-items-center rounded-lg [&_svg]:size-4" :class="decisionTypes.includes(type)?'bg-violet-100 text-violet-700':'bg-sky-100 text-sky-700'">@foreach(array_merge(\App\Models\Activity::ACTIVITY_TYPES,\App\Models\Activity::DECISION_TYPES) as $iconType)<span x-show="type===@js($iconType)">@include('activities._type_icon',['type'=>$iconType])</span>@endforeach</span>
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-bold leading-tight text-ink">@foreach(\App\Models\Activity::TYPES as $key=>$label)<span x-show="type===@js($key)">{{ $label }}</span>@endforeach</span>
                                    <span class="block text-[9px] leading-tight" :class="decisionTypes.includes(type)?'text-violet-600':'text-sky-600'" x-text="decisionTypes.includes(type)?'Memerlukan approval':'Aktivitas'"></span>
                                </span>
                            </span>
                            <svg class="size-4 shrink-0 text-slate-400 transition" :class="typePickerOpen&&'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                        </button>

                        <div x-show="typePickerOpen" x-cloak x-transition.origin.top.left @click.outside="typePickerOpen=false" class="absolute left-0 top-full z-50 mt-2 w-[min(760px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3"><div><h4 class="text-sm font-extrabold text-ink">Pilih aktivitas</h4><p class="mt-0.5 text-[10px] text-slate-400">Klik salah satu pilihan untuk melanjutkan.</p></div><button type="button" class="grid size-8 place-items-center rounded-full bg-slate-100 text-slate-500" @click="typePickerOpen=false">×</button></div>
                            <div class="scrollbar-thin max-h-[430px] overflow-y-auto p-4">
                                <div class="mb-3 text-[9px] font-black uppercase tracking-[.12em] text-slate-400">Aktivitas</div>
                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                    @foreach(\App\Models\Activity::ACTIVITY_TYPES as $key)
                                        @php($label = \App\Models\Activity::TYPES[$key])
                                        <button type="button" @click="type=@js($key);typePickerOpen=false" class="group flex min-h-20 flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-2 text-center transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md" :class="type===@js($key)&&'ring-2 ring-sky-500 border-sky-400'"><span class="grid size-9 place-items-center rounded-xl bg-sky-50 text-sky-600">@include('activities._type_icon',['type'=>$key])</span><span class="text-[10px] font-bold leading-tight text-slate-700">{{ $label }}</span></button>
                                    @endforeach
                                </div>
                                <div class="my-4 border-t border-slate-100"></div>
                                <div class="mb-3 flex items-center justify-between gap-2"><span class="text-[9px] font-black uppercase tracking-[.12em] text-slate-400">Perlu Approval</span><span class="text-[9px] text-slate-400">Pilih akun approver aktif setelah memilih jenis aktivitas</span></div>
                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                    @foreach(\App\Models\Activity::DECISION_TYPES as $key)
                                        @php($label = \App\Models\Activity::TYPES[$key])
                                        <button type="button" @click="type=@js($key);typePickerOpen=false" class="group flex min-h-20 flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-2 text-center transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md" :class="type===@js($key)&&'ring-2 ring-violet-500 border-violet-400'"><span class="grid size-9 place-items-center rounded-xl bg-violet-50 text-violet-600">@include('activities._type_icon',['type'=>$key])</span><span class="text-[10px] font-bold leading-tight text-slate-700">{{ $label }}</span></button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <p x-show="decisionTypes.includes(type)" x-cloak class="mt-2 text-[10px] font-semibold text-violet-600">Pilih approver aktif untuk memberikan keputusan.</p>
                    </div>
                    <div><label class="label">Waktu aktivitas *</label><input type="datetime-local" class="field" name="occurred_at" value="{{ old('occurred_at',$activity->occurred_at?->format('Y-m-d\TH:i')) }}" required></div>
                    <section x-show="type==='approval_special_price'" x-cloak class="md:col-span-2 rounded-xl border border-violet-200 bg-violet-50/40 p-4">
                        <div class="mb-4"><h4 class="text-xs font-extrabold text-violet-800">Produk yang diajukan</h4><p class="mt-1 text-[10px] text-slate-500">Pilih produk dari opportunity. Harga opportunity tidak berubah sebelum disetujui.</p></div>
                        <div x-show="!selectedOpportunity" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs font-semibold text-amber-700">Pilih opportunity terlebih dahulu.</div>
                        <div x-show="selectedOpportunity && !selectedOpportunityItems().length" class="rounded-xl border border-slate-200 bg-white p-4 text-xs text-slate-500">Opportunity ini belum memiliki produk.</div>
                        <div class="space-y-3">
                            <template x-for="item in selectedOpportunityItems()" :key="item.id">
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-start gap-3"><input type="checkbox" x-model="item.selected" class="mt-0.5 size-4 rounded border-slate-300 text-violet-600" :name="`special_price_items[${item.id}][selected]`" value="1"><div class="min-w-0 flex-1"><div class="text-xs font-extrabold text-ink" x-text="item.name"></div><div class="mt-1 text-[10px] text-slate-400"><span x-text="new Intl.NumberFormat('id-ID').format(item.quantity)"></span> <span x-text="item.unit"></span> · Penawaran saat ini <span class="font-bold" x-text="rupiah(item.unit_price)"></span> · Target <span x-text="rupiah(item.target_price)"></span></div></div></div>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2"><div><label class="label">Harga khusus yang diajukan *</label><div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Rp</span><input type="text" inputmode="numeric" data-money class="field !pl-9" :name="`special_price_items[${item.id}][requested_price]`" :value="item.requested_price" :required="item.selected" placeholder="0"></div></div><div><label class="label">Alasan pengajuan *</label><input class="field" :name="`special_price_items[${item.id}][reason]`" :value="item.reason" :required="item.selected" placeholder="Contoh: volume pembelian besar"></div></div>
                                </div>
                            </template>
                        </div>
                        @error('special_price_items')<p class="mt-2 text-[10px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                    </section>
                    @foreach(array_keys(\App\Models\Activity::APPROVAL_FIELDS) as $approvalType)
                        @continue($approvalType === 'approval_special_price')
                        <section x-show="type===@js($approvalType)" x-cloak class="md:col-span-2 rounded-xl border border-violet-200 bg-violet-50/40 p-4">
                            <div class="mb-4"><h4 class="text-xs font-extrabold text-violet-800">Detail pengajuan {{ \App\Models\Activity::TYPES[$approvalType] }}</h4><p class="mt-1 text-[10px] text-slate-500">Lengkapi data berikut agar approver dapat mengambil keputusan.</p></div>
                            <div class="grid gap-4 md:grid-cols-2">
                                @foreach(\App\Models\Activity::APPROVAL_FIELDS[$approvalType] as $fieldKey => $field)
                                    @continue($fieldKey === 'unit')
                                    @continue(str_starts_with($field['type'], 'computed_'))
                                    <div @class(['md:col-span-2' => $field['type'] === 'textarea' && collect(\App\Models\Activity::APPROVAL_FIELDS[$approvalType])->reject(fn($item, $key) => $key === 'unit' || $item['type'] === 'textarea' || str_starts_with($item['type'], 'computed_'))->count() % 2 === 0])>
                                        @if($fieldKey === 'quantity' && isset(\App\Models\Activity::APPROVAL_FIELDS[$approvalType]['unit']))
                                            @php($quantityLabel = $approvalType === 'approval_return' ? 'Jumlah barang yang dikembalikan' : 'Jumlah pembelian')
                                            <div class="grid grid-cols-[minmax(0,1fr)_120px] gap-2">
                                                <div><label class="label">{{ $quantityLabel }} *</label><input type="number" min="0" step="1" class="field" name="approval_details[quantity]" value="{{ old('approval_details.quantity') }}" placeholder="Contoh: 1000" :disabled="type!==@js($approvalType)" required></div>
                                                <div><label class="label">Satuan *</label><select class="field" name="approval_details[unit]" :disabled="type!==@js($approvalType)" required><option value="">Pilih</option>@foreach(['pcs','pack','roll','ctn','set','kg','bal'] as $unit)<option value="{{ $unit }}" @selected(old('approval_details.unit')===$unit)>{{ strtoupper($unit) }}</option>@endforeach</select></div>
                                            </div>
                                            <p class="mt-1.5 text-[9px] text-slate-400">{{ $approvalType === 'approval_return' ? 'Berapa banyak barang yang dikembalikan, misalnya 2 CTN.' : 'Perkiraan jumlah yang akan dibeli customer, misalnya 1.000 PCS.' }}</p>
                                            @error('approval_details.quantity')<p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                                            @error('approval_details.unit')<p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                                        @elseif($approvalType === 'approval_credit_limit' && $fieldKey === 'current_limit')
                                            <label class="label">{{ $field['label'] }}</label>
                                            <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span><input type="text" class="field !pl-9 bg-slate-100 text-slate-600" :value="new Intl.NumberFormat('id-ID').format(selectedCustomerCreditLimit())" readonly tabindex="-1"></div>
                                            <input type="hidden" name="approval_details[current_limit]" :value="selectedCustomerCreditLimit()" :disabled="type!==@js($approvalType)">
                                            <p class="mt-1.5 text-[9px] text-slate-400">Diambil otomatis dari data customer.</p>
                                        @elseif($field['type'] === 'textarea')
                                            <label class="label">{{ $field['label'] }} *</label>
                                            <textarea class="field min-h-[42px] resize-y py-2.5" rows="1" name="approval_details[{{ $fieldKey }}]" :disabled="type!==@js($approvalType)" required>{{ old("approval_details.$fieldKey") }}</textarea>
                                        @elseif($field['type'] === 'currency')
                                            <label class="label">{{ $field['label'] }} *</label>
                                            <div class="relative"><span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span><input type="text" inputmode="numeric" data-money class="field !pl-9" name="approval_details[{{ $fieldKey }}]" value="{{ old("approval_details.$fieldKey") }}" placeholder="0" :disabled="type!==@js($approvalType)" @input="creditTick++" required></div>
                                        @else
                                            <label class="label">{{ $field['label'] }} *</label>
                                            <input type="{{ $field['type'] }}" @if($field['type']==='number') min="0" step="1" @endif class="field" name="approval_details[{{ $fieldKey }}]" value="{{ old("approval_details.$fieldKey") }}" :disabled="type!==@js($approvalType)" @input="creditTick++" required>
                                        @endif
                                        @if($fieldKey !== 'quantity')@error("approval_details.$fieldKey")<p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $message }}</p>@enderror @endif
                                    </div>
                                @endforeach
                                @if($approvalType === 'approval_credit_limit')
                                    <div class="md:col-span-2 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-xl border border-slate-200 bg-white p-3"><div class="text-[9px] font-black uppercase tracking-wide text-slate-400">Sisa limit aktual</div><div class="mt-1 text-base font-extrabold" :class="remainingCredit() < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="rupiah(remainingCredit())"></div><div class="mt-1 text-[9px] text-slate-400">Batas kredit dikurangi piutang berjalan.</div></div>
                                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><div class="text-[9px] font-black uppercase tracking-wide text-amber-600">Total melebihi limit</div><div class="mt-1 text-base font-extrabold text-amber-700" x-text="rupiah(creditOverLimit())"></div><div class="mt-1 text-[9px] text-amber-600">Dihitung otomatis dari nilai pesanan baru.</div></div>
                                    </div>
                                @endif
                                @if($approvalType === 'approval_payment_term')
                                    <div class="md:col-span-2 rounded-xl border border-violet-200 bg-white p-3">
                                        <div class="text-[9px] font-black uppercase tracking-wide text-violet-500">Tambahan tempo</div>
                                        <div class="mt-1 text-base font-extrabold text-violet-700"><span x-text="additionalTermDays()"></span> hari</div>
                                        <div class="mt-1 text-[9px] text-slate-400">Dihitung otomatis dari tempo yang diajukan dikurangi tempo saat ini.</div>
                                    </div>
                                @endif
                            </div>
                        </section>
                    @endforeach
                    <div class="md:col-span-2">
                        @if($completesFollowUp)
                            <input type="hidden" name="completes_follow_up_id" value="{{ $completesFollowUp->id }}">
                        @else
                        <label class="label">Jadwal follow-up yang diselesaikan</label>
                        <select class="field" name="completes_follow_up_id" x-model="selectedFollowUp" :disabled="!selectedCustomer">
                            <option value="" x-text="!selectedCustomer ? 'Pilih customer terlebih dahulu' : (followUpsLoading ? 'Memuat jadwal follow-up...' : 'Tidak menyelesaikan jadwal follow-up tertentu')"></option>
                            <template x-for="item in pendingFollowUps" :key="item.id"><option :value="String(item.id)" x-text="(item.overdue ? 'TERLAMBAT · ' : '') + item.due_at + ' · ' + item.summary"></option></template>
                        </select>
                        <div class="mt-2 rounded-lg bg-sky-50 px-3 py-2 text-[10px] leading-relaxed text-sky-700" x-show="pendingFollowUps.length">Pilih jadwal jika aktivitas ini merupakan pengerjaannya. Setelah tersimpan, pengingat tersebut otomatis selesai.</div>
                        <div class="mt-2 text-[10px] text-slate-400" x-show="!followUpsLoading && selectedCustomer && !pendingFollowUps.length">Customer ini tidak memiliki jadwal follow-up yang masih terbuka.</div>
                        @endif
                    </div>
                    <div class="md:col-span-2">
                        <label class="label">Judul / ringkasan *</label>
                        <input class="field disabled:bg-slate-100 disabled:text-slate-600 @error('summary') !border-rose-400 @enderror" name="summary" value="{{ old('summary', $activity->summary) }}" placeholder="Contoh: Visit pembahasan kebutuhan packaging Q3" required>
                        @error('summary')<p class="mt-1.5 text-[10px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2"><label class="label" x-text="decisionTypes.includes(type)?'Catatan tambahan':'Catatan hasil aktivitas'"></label><textarea x-model="activityDetail" class="field" rows="4" name="detail" :placeholder="decisionTypes.includes(type)?'Informasi tambahan untuk approver (opsional)':'Apa yang dilakukan atau dibahas?'"></textarea></div>
                    <div class="md:col-span-2" x-show="!decisionTypes.includes(type)" x-cloak><label class="label">Hasil / keputusan <span x-show="baseEvidenceRequired">*</span></label><textarea x-model="activityResult" class="field" rows="3" name="result" :required="baseEvidenceRequired" placeholder="Apa hasil atau keputusan dari aktivitas ini?"></textarea></div>
                </div>
            </section>

            <section class="card overflow-hidden">
                <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="section-title">Bukti aktivitas</h3><p class="mt-1 text-[10px] text-slate-400">Foto aktivitas atau dokumen pendukung.</p></div><span x-show="requiresEvidence()" class="badge bg-rose-50 text-rose-600"><span class="size-1.5 rounded-full bg-current"></span>Wajib · {{ $evidenceDepartments->join(', ') }}</span><span x-show="!requiresEvidence()" x-cloak class="badge bg-slate-100 text-slate-500" x-text="decisionTypes.includes(type) ? 'Opsional untuk approval' : 'Opsional untuk divisi Anda'"></span></div>
                <div class="p-5">
                    <div
                        class="group grid min-h-40 cursor-pointer place-items-center rounded-xl border-2 border-dashed p-6 text-center transition"
                        :class="dragging ? 'border-brand-500 bg-brand-50' : (requiresEvidence() && !hasImage() ? 'border-rose-200 bg-rose-50/30' : 'border-slate-200 hover:border-brand-300 hover:bg-brand-50/30')"
                        @click="$refs.evidence.click()"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="dragging = false; setFiles($event.dataTransfer.files)"
                        role="button"
                        tabindex="0"
                        @keydown.enter.prevent="$refs.evidence.click()"
                    >
                        <div><span class="mx-auto grid size-11 place-items-center rounded-xl bg-brand-50 text-xl text-brand-600">↑</span><div class="mt-3 text-xs font-extrabold text-ink">Upload dari galeri atau file</div><div class="mt-1 text-[10px] text-slate-400">JPG, PNG, WEBP, HEIC, HEIF, atau PDF · maksimal 30 MB per file</div><div x-show="requiresEvidence() && !hasImage()" class="mt-3 text-[10px] font-bold text-rose-600">Divisi Anda wajib menyertakan minimal satu gambar.</div></div>
                    </div>
                    <input x-ref="evidence" type="file" multiple name="attachments[]" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif,application/pdf" class="hidden" @change="refreshFiles()">
                    <input x-ref="camera" type="file" accept="image/*" capture="environment" class="hidden" @change="handleCameraFiles($event.target.files)">
                    <input type="hidden" name="attachment_metadata" :value="JSON.stringify(files.map(file => ({ name: file.name, size: file.size, type: file.type, lastModified: file.lastModified, source: file.source, latitude: file.latitude, longitude: file.longitude, accuracy: file.accuracy, locationRecordedAt: file.locationRecordedAt })))">

                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button type="button" class="btn-secondary w-full" @click="$refs.evidence.click()"><span class="text-base">↑</span> Pilih dari galeri/file</button>
                        <button type="button" class="btn-primary w-full" @click="openCamera()" :disabled="cameraStatus === 'locating'"><span class="text-base">⌾</span><span x-text="cameraStatus === 'locating' ? 'Mencari lokasi...' : 'Buka kamera + lokasi'"></span></button>
                    </div>
                    <div class="mt-2 rounded-lg bg-sky-50 px-3 py-2 text-[9px] leading-relaxed text-sky-700"><span x-show="cameraStatus === 'idle'">Kamera akan meminta izin lokasi. Koordinat dan akurasi GPS disimpan bersama foto untuk kebutuhan tracking.</span><span x-show="cameraStatus === 'ready'">Lokasi berhasil didapatkan. Ambil foto melalui kamera.</span><span x-show="cameraStatus === 'denied'">Izin lokasi ditolak. Foto tetap dapat diambil, tetapi lokasi perangkat tidak akan tercatat.</span><span x-show="cameraStatus === 'unsupported'">Perangkat atau browser tidak mendukung lokasi. Foto tetap dapat diambil tanpa koordinat.</span></div>

                    <div x-show="files.length" x-cloak class="mt-4">
                        <div class="mb-2 flex items-center justify-between"><span class="text-[10px] font-bold text-slate-500"><span x-text="files.length"></span> file dipilih</span><button type="button" class="text-[10px] font-bold text-rose-500" @click="setFiles([])">Hapus semua</button></div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <template x-for="(file, index) in files" :key="file.name + file.size">
                                <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white">
                                    <div class="grid h-32 place-items-center bg-slate-50">
                                        <img x-show="file.url" :src="file.url" :alt="file.name" class="h-full w-full object-cover">
                                        <div x-show="!file.url" class="text-center"><span class="mx-auto grid size-10 place-items-center rounded-lg text-[9px] font-black" :class="isHeic(file) ? 'bg-sky-50 text-sky-600' : 'bg-rose-50 text-rose-600'" x-text="isHeic(file) ? 'HEIC' : 'PDF'"></span><span class="mt-2 block text-[9px] text-slate-400" x-text="file.previewLoading ? 'Menyiapkan preview...' : (isHeic(file) ? 'Preview HEIC tidak tersedia' : 'Dokumen PDF')"></span></div>
                                    </div>
                                    <div class="p-3"><div class="truncate text-[10px] font-bold text-ink" x-text="file.name"></div><div class="mt-1 flex flex-wrap items-center gap-1.5 text-[9px] text-slate-400"><span x-text="fileSize(file.size)"></span><span class="badge" :class="file.source === 'camera' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'" x-text="file.source === 'camera' ? 'Kamera langsung' : 'Upload file'"></span><span x-show="file.latitude" class="badge bg-sky-50 text-sky-600">GPS ±<span x-text="Math.round(file.accuracy || 0)"></span>m</span></div></div>
                                    <button type="button" class="absolute right-2 top-2 grid size-7 place-items-center rounded-full bg-slate-950/70 text-xs text-white backdrop-blur hover:bg-rose-600" @click.stop="removeFile(index)" aria-label="Hapus file">×</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="card p-5">
                <h3 class="section-title" x-text="decisionTypes.includes(type) ? 'Pilih approver *' : 'Kolaborasi'"></h3>
                <p class="mt-1 text-[10px] leading-relaxed text-slate-400" x-text="decisionTypes.includes(type) ? 'Pilih minimal satu approver aktif yang akan memberikan keputusan.' : 'Libatkan rekan yang perlu mengetahui dan mendiskusikan aktivitas ini.'"></p>
                <div class="relative mt-4" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="field flex items-center justify-between gap-3 text-left" :class="decisionTypes.includes(type) && !hasSelectedApprover() ? '!border-rose-300 !bg-rose-50/40' : ''" @click="open = !open">
                        <span class="min-w-0 flex-1 truncate" x-text="selectedCollaborators.length ? selectedCollaborators.length + ' orang dipilih' : (decisionTypes.includes(type) ? 'Pilih approver (wajib)' : 'Pilih rekan (opsional)')"></span>
                        <svg class="size-4 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" x-cloak class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                        @forelse($collaborationUsers as $collaborator)
                            @php($isApprover = $collaborator->canApprove())
                            <label x-show="!decisionTypes.includes(type) || @js($isApprover)" class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 hover:bg-slate-50">
                                <input type="checkbox" name="participant_ids[]" value="{{ $collaborator->id }}" x-model="selectedCollaborators" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-[9px] font-black text-brand-700">{{ collect(explode(' ', $collaborator->name))->take(2)->map(fn($word) => mb_substr($word, 0, 1))->join('') }}</span>
                                <span class="min-w-0 flex-1"><span class="block truncate text-[11px] font-bold text-slate-700">{{ $collaborator->name }}</span><span class="block truncate text-[9px] text-slate-400">{{ $collaborator->employee_id ?: ucfirst($collaborator->user_type) }} · {{ $collaborator->roleNames() ?: ucfirst(str_replace('_',' ',$collaborator->authority_level)) }}</span></span>
                                @if($isApprover)<span x-show="decisionTypes.includes(type)" x-cloak class="badge bg-violet-50 text-violet-600">Approver</span>@endif
                            </label>
                        @empty
                            <div class="p-4 text-center text-[10px] text-slate-400">Belum ada rekan yang dapat dipilih.</div>
                        @endforelse
                    </div>
                </div>
                <p x-show="!decisionTypes.includes(type)" class="mt-2 text-[9px] leading-relaxed text-slate-400">Rekan terpilih akan menerima notifikasi dan hanya mendapat akses ke aktivitas ini.</p>
                <p x-show="decisionTypes.includes(type) && !hasSelectedApprover()" x-cloak class="mt-2 text-[9px] font-semibold leading-relaxed text-rose-600">Wajib memilih minimal satu akun berlabel Approver.</p>
                <p x-show="decisionTypes.includes(type) && hasSelectedApprover()" x-cloak class="mt-2 text-[9px] font-semibold leading-relaxed text-emerald-600">Approver sudah dipilih dan akan menerima notifikasi.</p>
                @error('participant_ids')<p class="mt-2 text-[9px] font-semibold text-rose-600">{{ $message }}</p>@enderror
            </section>
            <section class="card p-5"><h3 class="section-title">Tindak lanjut</h3><div class="mt-5 space-y-4"><div><label class="label">Next action</label><textarea class="field" rows="4" name="next_action" placeholder="Apa yang harus dilakukan selanjutnya?">{{ old('next_action', $activity->next_action) }}</textarea></div><div><label class="label">Jadwal follow-up berikutnya</label><input type="datetime-local" class="field" name="next_follow_up_at" value="{{ old('next_follow_up_at', $activity->next_follow_up_at?->format('Y-m-d\TH:i')) }}"></div></div></section>
            <div class="flex gap-3"><a href="{{ route('activities.index') }}" class="btn-secondary flex-1">Batal</a><button type="submit" class="btn-primary flex-1 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none" :disabled="(requiresEvidence() && !hasImage()) || (baseEvidenceRequired && !decisionTypes.includes(type) && !activityResult.trim()) || (decisionTypes.includes(type) && !hasSelectedApprover())">Simpan aktivitas</button></div>
            <p x-show="requiresEvidence() && !hasImage()" x-cloak class="text-center text-[10px] font-semibold text-rose-500">Tambahkan minimal satu gambar sesuai kebijakan divisi.</p>
        </aside>
    </div>
</form>
@endsection
