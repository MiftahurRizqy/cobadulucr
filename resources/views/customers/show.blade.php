@extends('layouts.app')
@section('title',$customer->company_name)
@section('eyebrow','CRM / Customer / '.$customer->customer_id)
@section('content')

<div class="mb-4 flex justify-end md:-mt-14" x-data="{ createOpen: false, documentsOpen: false, selectedDocument: {{ (int) ($customer->attachments->first()?->id ?? 0) }} }">
    <div class="flex items-center gap-2">
        @if($customer->status !== 'inactive')<a href="{{ route('activities.create',['customer'=>$customer]) }}" class="btn-primary">+ Catat aktivitas</a>@else<span class="inline-flex h-10 cursor-not-allowed items-center rounded-lg bg-slate-100 px-4 text-xs font-bold text-slate-400" title="Aktifkan customer untuk mencatat aktivitas">+ Catat aktivitas</span>@endif
        <div class="relative">
            <button type="button" class="grid size-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700" @click="createOpen=!createOpen" title="Aksi lainnya" aria-label="Aksi lainnya"><svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><circle cx="4" cy="10" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="16" cy="10" r="1.5"/></svg></button>
            <div x-show="createOpen" x-cloak x-transition.origin.top.right @click.outside="createOpen=false" class="absolute right-0 top-12 z-40 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl">
                <a href="{{ route('opportunities.create',['customer'=>$customer]) }}" class="block rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 hover:bg-brand-50 hover:text-brand-700">Buat opportunity</a>
                <a href="{{ route('tasks.create',['customer'=>$customer]) }}" class="block rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 hover:bg-brand-50 hover:text-brand-700">Buat task</a>
                <div class="my-1 border-t border-slate-100"></div>
                @if(auth()->user()->canAccess('customers.edit'))<a href="{{ route('customers.edit',$customer) }}" class="block rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 hover:bg-brand-50 hover:text-brand-700">Edit data customer</a>@endif
                <button type="button" @click="createOpen=false; documentsOpen=true" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-xs font-bold text-slate-700 hover:bg-brand-50 hover:text-brand-700"><span>Dokumen customer</span><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] text-slate-500">{{ $customer->attachments->count() }}</span></button>
            </div>
        </div>
    </div>

    <template x-teleport="body">
        <div x-show="documentsOpen" x-cloak x-transition.opacity @keydown.escape.window="documentsOpen=false" @click.self="documentsOpen=false" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6" role="dialog" aria-modal="true" aria-label="Dokumen customer">
            <div x-show="documentsOpen" x-transition class="customer-document-print-root flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-slate-50 shadow-2xl">
                <header class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4">
                    <div><h3 class="text-sm font-extrabold text-ink">Dokumen customer</h3><p class="mt-1 text-[10px] text-slate-400">{{ $customer->company_name }} · {{ $customer->attachments->count() }} dokumen</p></div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('customers.documents.store', $customer) }}" enctype="multipart/form-data" class="contents">@csrf<label class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 text-[10px] font-bold text-slate-600 shadow-sm hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700" title="Tambah dokumen"><svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg><span class="hidden sm:inline">Tambah dokumen</span><input type="file" name="supporting_document" accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden" onchange="if(this.files.length) this.form.submit()"></label></form>
                        @foreach($customer->attachments as $document)
                            <button x-show="selectedDocument === {{ $document->id }}" x-cloak type="button" onclick="printCustomerDocument(@js(Storage::disk('public')->url($document->path)), @js($document->mime_type), this)" class="inline-flex size-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700 disabled:cursor-wait disabled:opacity-60" title="Cetak dokumen" aria-label="Cetak {{ $document->name }}"><svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7V3h8v4M6 14H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2"/><path d="M6 11h8v6H6z"/><path d="M15 9h.01"/></svg></button>
                        @endforeach
                        <button type="button" @click="documentsOpen=false" class="inline-flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Tutup"><svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button>
                    </div>
                </header>
                <div class="grid min-h-0 flex-1 overflow-hidden lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside class="max-h-52 overflow-y-auto border-b border-slate-200 bg-white p-3 lg:max-h-none lg:border-b-0 lg:border-r">
                        <div class="space-y-2">
                            @foreach($customer->attachments as $document)
                                <button type="button" @click="selectedDocument={{ $document->id }}" :class="selectedDocument === {{ $document->id }} ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'" class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-lg {{ str_starts_with($document->mime_type ?? '', 'image/') ? 'bg-sky-50 text-sky-600' : 'bg-rose-50 text-rose-600' }} text-[9px] font-black">{{ str_starts_with($document->mime_type ?? '', 'image/') ? 'IMG' : 'PDF' }}</span>
                                    <span class="min-w-0"><span class="block truncate text-[11px] font-bold">{{ $document->name }}</span><span class="mt-1 block text-[9px] text-slate-400">{{ number_format($document->size / 1024, 0, ',', '.') }} KB</span></span>
                                </button>
                            @endforeach
                            @if($customer->attachments->isEmpty())
                                <div class="rounded-xl border border-dashed border-slate-300 p-5 text-center text-[10px] leading-relaxed text-slate-400">Belum ada dokumen customer.<br>Gunakan tombol Tambah dokumen.</div>
                            @endif
                        </div>
                    </aside>
                    <div class="min-h-[55vh] overflow-auto bg-slate-200/60 p-3 sm:p-5">
                        @foreach($customer->attachments as $document)
                            @php
                                $documentUrl = Storage::disk('public')->url($document->path);
                            @endphp
                            <div x-show="selectedDocument === {{ $document->id }}" x-cloak class="customer-document-preview h-full">
                                @if(str_starts_with($document->mime_type ?? '', 'image/'))
                                    <div class="grid min-h-[55vh] place-items-center"><img src="{{ $documentUrl }}" alt="{{ $document->name }}" class="max-h-[70vh] max-w-full rounded-xl bg-white object-contain shadow-sm"></div>
                                @else
                                    <iframe id="customer-document-frame-{{ $document->id }}" src="{{ $documentUrl }}#toolbar=0" title="{{ $document->name }}" class="h-[70vh] w-full rounded-xl border border-slate-200 bg-white shadow-sm"></iframe>
                                @endif
                            </div>
                        @endforeach
                        @if($customer->attachments->isEmpty())
                            <div class="grid min-h-[55vh] place-items-center text-center"><div><div class="text-sm font-bold text-slate-600">Belum ada dokumen</div><p class="mt-1 text-xs text-slate-400">Tambahkan PDF atau gambar melalui tombol di atas.</p></div></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<template x-teleport="body">
    <div data-customer-document-print-area class="customer-document-print-area" aria-hidden="true"></div>
</template>

<div class="space-y-5">
    <div class="grid items-start gap-5 xl:grid-cols-[minmax(380px,.72fr)_minmax(0,1.28fr)] xl:grid-rows-[1fr] xl:items-stretch">
    <aside class="h-full">
        @php
            $statusLabel = ['pareto' => 'Cust Pareto', 'active' => 'Cust Aktif', 'inactive' => 'Cust Non Aktif', 'risky' => 'Cust Risky'][$customer->status] ?? ucfirst($customer->status);
            $identityDetails = [
                ['Nomor WhatsApp', $customer->phone],
                ['Email', $customer->email],
                ['Kota/Kabupaten', $customer->city],
                ['Area', $customer->area?->name],
                ['Jenis customer', $customer->business_type],
                ['Sales utama', $customer->salesOwner?->name],
                ['Status', $statusLabel],
            ];
            $transactionDetails = [
                ['Batas kredit', $customer->credit_limit !== null ? 'Rp '.number_format((float) $customer->credit_limit, 0, ',', '.') : null],
                ['Tempo pembayaran', $customer->payment_term_days !== null ? $customer->payment_term_days.' hari' : null],
                ['Estimasi pembelian bulanan', $customer->estimated_monthly_purchase !== null ? 'Rp '.number_format((float) $customer->estimated_monthly_purchase, 0, ',', '.') : null],
                ['Tindak lanjut', $customer->next_follow_up_at?->translatedFormat('d M Y, H:i')],
            ];
        @endphp
        <section class="hidden">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 class="text-sm font-extrabold text-ink">Informasi customer</h3>
                    <p class="mt-1 text-[11px] text-slate-400">Identitas, lokasi, dan penanggung jawab customer.</p>
                </div>
                <span aria-hidden="true"></span>
            </div>
            <dl class="grid gap-x-6 gap-y-4 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($identityDetails as [$label,$value])
                    <div><dt class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">{{ $label }}</dt><dd class="mt-1 break-words text-xs font-semibold text-slate-700">{{ filled($value) ? $value : '—' }}</dd></div>
                @endforeach
                <div class="sm:col-span-2 lg:col-span-4"><dt class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Alamat lengkap</dt><dd class="mt-1 break-words text-xs font-semibold leading-relaxed text-slate-700">{{ $customer->address ?: '—' }}</dd></div>
            </dl>
            <div class="border-t border-slate-100 bg-slate-50/50 px-5 py-4">
                <div class="mb-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Informasi transaksi</div>
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($transactionDetails as [$label,$value])
                        <div><dt class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">{{ $label }}</dt><dd class="mt-1 break-words text-xs font-semibold text-slate-700">{{ filled($value) ? $value : '—' }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </section>
        @php
            $location = collect([$customer->city, $customer->area?->name])->filter()->join(' · ');
            $primaryDetails = [
                ['Nomor WhatsApp', $customer->phone, ''],
                ['Email', $customer->email, ''],
                ['Lokasi', $location, ''],
                ['Jenis customer', $customer->business_type, ''],
                ['Sales utama', $customer->salesOwner?->name, ''],
                ['Tindak lanjut', $customer->next_follow_up_at?->translatedFormat('d M Y, H:i'), ''],
            ];
            $secondaryDetails = [
                ['Nama legal', $customer->legal_name, ''],
                ['NPWP', $customer->npwp, ''],
                ['Alamat', $customer->address, ''],
                ['Batas kredit', $customer->credit_limit !== null ? 'Rp '.number_format((float) $customer->credit_limit, 0, ',', '.') : null, ''],
                ['Tempo bayar', $customer->payment_term_days !== null ? $customer->payment_term_days.' hari' : null, ''],
                ['Estimasi pembelian bulanan', $customer->estimated_monthly_purchase !== null ? 'Rp '.number_format((float) $customer->estimated_monthly_purchase, 0, ',', '.') : null, ''],
            ];
            $secondaryDetails = collect($secondaryDetails);
        @endphp
        <section class="card flex h-full w-full flex-col overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-extrabold text-ink">Informasi customer</h3>
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">{{ $statusLabel }}</span>
                </div>
                <span aria-hidden="true"></span>
            </div>
            <dl class="grid flex-1 content-evenly gap-x-5 gap-y-3 px-4 py-3 sm:grid-cols-2">
                @foreach($primaryDetails as [$label,$value,$span])
                    <div class="{{ $span }}">
                        <dt class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">{{ $label }}</dt>
                        <dd class="mt-1 line-clamp-2 break-words text-xs font-semibold leading-4 {{ filled($value) ? 'text-slate-700' : 'text-slate-400' }}" title="{{ $value }}">{{ filled($value) ? $value : ($label === 'Tindak lanjut' ? 'Belum dijadwalkan' : 'Belum diisi') }}</dd>
                    </div>
                @endforeach
                @foreach($secondaryDetails as [$label,$value,$span])
                    <div class="{{ $span }}"><dt class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">{{ $label }}</dt><dd class="mt-1 line-clamp-2 break-words text-xs font-semibold leading-4 {{ filled($value) ? 'text-slate-700' : 'text-slate-400' }}" title="{{ $value }}">{{ filled($value) ? $value : 'Belum diisi' }}</dd></div>
                @endforeach
            </dl>
        </section>
    </aside>

    <div class="flex h-full min-w-0 flex-col gap-5">
        @if(count($customer->interestItems()) && $opportunityOptions->isEmpty())
            <section class="card overflow-hidden">
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2"><h3 class="section-title">Kebutuhan awal</h3>@if($customer->converted_from_lead_id)<span class="badge bg-sky-50 text-sky-600">Dari lead</span>@endif</div>
                        <p class="mt-1 text-xs text-slate-400">Informasi minat customer sebelum dibuat menjadi opportunity.</p>
                    </div>
                    <a href="{{ route('opportunities.create',['customer'=>$customer,'from_initial_need'=>1]) }}" class="btn-primary">Buat opportunity dari kebutuhan ini</a>
                </div>
                <div class="divide-y divide-slate-100 border-t border-slate-100">
                    @foreach($customer->interestItems() as $interest)
                        <div class="grid gap-2 p-5 sm:grid-cols-[minmax(0,1fr)_220px] sm:items-center">
                            <div><div class="label">Produk yang diminati</div><div class="mt-2 text-sm font-bold text-ink">{{ $interest['product_name'] }}</div></div>
                            <div><div class="label">Est. Qty/Bulan</div><div class="mt-2 text-sm font-bold text-ink">{{ !empty($interest['estimated_need']) ? number_format($interest['estimated_need'],0,',','.').' '.(\App\Models\Opportunity::QUANTITY_UNITS[$interest['estimated_need_unit'] ?? 'pcs'] ?? ucfirst($interest['estimated_need_unit'] ?? 'pcs')) : 'Belum diisi' }}</div></div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @include('customers._opportunity_workspace')
    </div>
    </div>

        <section class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Aktivitas customer</h3><p class="mt-1 text-xs text-slate-400">Riwayat komunikasi, tindak lanjut, dan bukti aktivitas.</p></div><div class="flex items-center gap-2"><span class="badge bg-sky-50 text-sky-600">{{ $activities->total() }} aktivitas</span><a href="{{ route('activities.create',['customer'=>$customer]) }}" class="text-xs font-bold text-brand-600">+ Catat aktivitas</a></div></header>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] table-fixed text-left">
                    <thead class="table-head"><tr><th class="w-[120px] px-4 py-3">Waktu</th><th class="w-[240px] px-4 py-3">Aktivitas</th><th class="px-4 py-3">Hasil</th><th class="w-[175px] px-4 py-3">Follow-up</th><th class="w-[150px] px-4 py-3">PIC</th><th class="w-[80px] px-4 py-3 text-center">Bukti</th><th class="w-[80px] px-4 py-3 text-center">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($activities as $activity)
                        @php
                            $typeStyle = in_array($activity->type, \App\Models\Activity::DECISION_TYPES, true) ? 'bg-violet-100 text-violet-700' : 'bg-sky-100 text-sky-700';
                            $followUpPending = $activity->next_follow_up_at && ! $activity->follow_up_completed_at;
                            $followUpOverdue = $followUpPending && $activity->next_follow_up_at->isPast();
                        @endphp
                        <tr class="activity-row" data-activity-evidence-scope x-data="{ detailOpen: false, inviteOpen: false }">
                            <td class="px-4 py-3 align-top"><div class="text-xs font-bold text-ink">{{ $activity->occurred_at->translatedFormat('d M Y') }}</div><div class="mt-1 text-[10px] text-slate-400">{{ $activity->occurred_at->format('H:i') }}</div></td>
                            <td class="px-4 py-3 align-top"><div class="truncate text-xs font-extrabold text-ink">{{ $activity->summary }}</div><div class="mt-1.5 flex flex-wrap gap-1"><span class="badge {{ $typeStyle }}">{{ \App\Models\Activity::TYPES[$activity->type] ?? $activity->type }}</span></div></td>
                            <td class="px-4 py-3 align-top"><p class="line-clamp-2 text-xs leading-relaxed text-slate-600">{{ $activity->result ?: 'Belum ada hasil' }}</p></td>
                            <td class="px-4 py-3 align-top">@if($activity->next_follow_up_at)<div class="text-xs font-bold {{ $followUpOverdue ? 'text-rose-600' : ($activity->follow_up_completed_at ? 'text-emerald-600' : 'text-slate-700') }}">{{ $activity->next_follow_up_at->translatedFormat('d M Y') }}</div><div class="mt-1 text-[10px] {{ $followUpOverdue ? 'text-rose-500' : 'text-slate-400' }}">{{ $activity->follow_up_completed_at ? 'Selesai' : ($followUpOverdue ? 'Terlambat' : $activity->next_follow_up_at->format('H:i')) }}</div>@else<span class="text-[10px] text-slate-400">Tidak dijadwalkan</span>@endif</td>
                            <td class="px-4 py-3 align-top"><div class="truncate text-xs font-bold text-slate-700">{{ $activity->user->name }}</div><div class="mt-1 text-[9px] text-slate-400">{{ $activity->user->employee_id ?: 'Akun CRM' }}</div></td>
                            <td class="px-4 py-3 text-center align-top">@if($activity->attachments->isNotEmpty())<span class="badge bg-cyan-50 text-cyan-600">{{ $activity->attachments->count() }}</span>@else<span class="text-slate-300">—</span>@endif</td>
                            <td class="px-4 py-3 text-center align-top"><button type="button" data-preload-activity-evidence class="btn-secondary h-8 px-2.5 text-[10px]" @click="detailOpen=true">Detail</button>
                                <div x-show="detailOpen" x-cloak x-transition.opacity @keydown.escape.window="detailOpen=false" @click.self="detailOpen=false" class="fixed inset-0 z-[110] grid place-items-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6">
                                    <div x-show="detailOpen" x-transition class="flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-slate-50 text-left shadow-2xl">
                                        <header class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4"><div><h3 class="text-sm font-extrabold text-ink">Rincian aktivitas</h3><p class="mt-1 text-[10px] text-slate-400">{{ $activity->summary }} · {{ $activity->user->name }}</p></div><div class="flex items-center gap-2"><span class="rounded-lg bg-slate-100 px-3 py-2 text-[10px] font-bold text-slate-500">#ACT-{{ str_pad($activity->id,5,'0',STR_PAD_LEFT) }}</span><button type="button" class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 p-0 text-slate-500" @click="detailOpen=false" aria-label="Tutup"><svg class="block size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button></div></header>
                                        <div class="overflow-y-auto p-5">
                                            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(360px,.9fr)]">
                                                <div><div class="overflow-hidden rounded-xl border border-slate-200 bg-white"><table class="w-full text-left text-xs"><tbody class="divide-y divide-slate-100"><tr><th class="w-40 bg-slate-50 px-4 py-3 font-bold text-slate-500">Judul</th><td class="px-4 py-3 font-bold text-ink">{{ $activity->summary }}</td></tr><tr><th class="bg-slate-50 px-4 py-3 font-bold text-slate-500">Catatan</th><td class="whitespace-pre-line px-4 py-3 leading-relaxed text-slate-700">{{ $activity->detail ?: 'Tidak diisi' }}</td></tr><tr class="bg-emerald-50/60"><th class="px-4 py-3 font-bold text-emerald-700">Hasil</th><td class="whitespace-pre-line px-4 py-3 font-semibold leading-relaxed text-slate-700">{{ $activity->result ?: (in_array($activity->type, \App\Models\Activity::DECISION_TYPES, true) ? 'Menunggu keputusan approver' : 'Tidak diisi') }}</td></tr><tr><th class="bg-slate-50 px-4 py-3 font-bold text-slate-500">Next action</th><td class="whitespace-pre-line px-4 py-3 leading-relaxed text-slate-700">{{ $activity->next_action ?: 'Tidak diisi' }}</td></tr><tr><th class="bg-slate-50 px-4 py-3 font-bold text-slate-500">Follow-up</th><td class="px-4 py-3 text-slate-700">{{ $activity->next_follow_up_at?->translatedFormat('d M Y, H:i') ?? 'Tidak dijadwalkan' }}</td></tr><tr><th class="bg-slate-50 px-4 py-3 font-bold text-slate-500">Opportunity</th><td class="px-4 py-3 text-slate-700">{{ $activity->opportunity?->title ?? 'Tanpa opportunity' }}</td></tr></tbody></table></div>@include('activities._approval_details', ['activity' => $activity])</div>
                                                <div>@if($activity->attachments->isNotEmpty())@include('activities._attachments', ['activity' => $activity, 'evidenceInitiallyOpen' => true])@else<div class="grid min-h-40 place-items-center rounded-xl border border-dashed border-slate-300 bg-white text-xs text-slate-400">Tidak ada bukti aktivitas.</div>@endif</div>
                                            </div>
                                            @if($followUpPending)<div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border {{ $followUpOverdue ? 'border-rose-100 bg-rose-50/60' : 'border-sky-100 bg-sky-50/50' }} p-4"><div class="text-xs font-bold {{ $followUpOverdue ? 'text-rose-600' : 'text-sky-600' }}">{{ $followUpOverdue ? 'Follow-up terlambat' : 'Follow-up terjadwal' }}</div><a href="{{ route('activities.follow-up', $activity) }}" class="btn-primary h-9">Kerjakan follow-up</a></div>@endif
                                            @include('activities._discussion', compact('activity', 'participantUsers', 'activityDiscussionUsers'))
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-10 text-center text-sm text-slate-400">Belum ada aktivitas customer.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($activities->total())<footer class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 border-t border-slate-100 px-5 py-4"><div class="text-[10px] text-slate-400">Menampilkan {{ $activities->firstItem() }}–{{ $activities->lastItem() }} dari {{ $activities->total() }}</div>@if($activities->hasPages())<div class="justify-self-center">{{ $activities->links('vendor.pagination.crm-compact') }}</div>@endif<div aria-hidden="true"></div></footer>@endif
        </section>
</div>

<style>
.customer-document-print-area { display: none; }
@media print {
    @page { margin: 0; }
    body > *:not(.customer-document-print-area) { display: none !important; }
    .customer-document-print-area { display: block !important; }
    .customer-document-print-page { display: grid; width: 100%; min-height: 100vh; place-items: center; break-after: page; page-break-after: always; overflow: hidden; }
    .customer-document-print-page:last-child { break-after: auto; page-break-after: auto; }
    .customer-document-print-page img,
    .customer-document-print-canvas { display: block; width: 100%; height: auto; max-width: 100%; max-height: 100vh; object-fit: contain; }
}
</style>
@endsection
