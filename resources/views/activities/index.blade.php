@extends('layouts.app')
@section('title','Aktivitas')
@section('eyebrow','CRM · Pelacakan aktivitas')
@section('content')

@php
    $periodLabels = ['today'=>'Hari ini','this_week'=>'Minggu ini','this_month'=>'Bulan ini','last_month'=>'Bulan lalu','this_year'=>'Tahun ini','custom'=>'Rentang tanggal'];
    $activeFilterCount = collect([request('search'), request('type'), request('user_id'), request('period'), $canReviewEvidenceIntegrity && request()->boolean('needs_review') ? 'needs_review' : null])->filter(fn($value) => filled($value))->count();
@endphp
<form class="card relative z-20 mb-5 overflow-visible p-3" x-data="{ advancedOpen: false, period: @js($selectedPeriod ?? '') }" @keydown.escape.window="advancedOpen=false">
    <div class="flex flex-col gap-2 sm:flex-row">
        <div class="relative min-w-0 flex-1"><svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><input class="field h-10 pl-9 text-sm" name="search" value="{{ request('search') }}" placeholder="Cari aktivitas, hasil, atau customer..."></div>
        <div class="flex gap-2"><button type="button" class="btn-secondary relative h-10 min-w-28" @click="advancedOpen=!advancedOpen"><svg class="mr-1.5 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filter @if($activeFilterCount)<span class="ml-1.5 grid size-5 place-items-center rounded-full bg-brand-600 text-[9px] font-black text-white">{{ $activeFilterCount }}</span>@endif</button><button class="btn-primary h-10 min-w-20">Cari</button></div>
    </div>
    <div x-show="advancedOpen" x-cloak x-transition.origin.top.right @click.outside="advancedOpen=false" class="absolute right-3 top-[58px] z-50 w-[min(720px,calc(100%-24px))] rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl">
        <div class="mb-4 flex items-center justify-between gap-3"><div><h3 class="text-sm font-extrabold text-ink">Filter aktivitas</h3><p class="mt-0.5 text-[10px] text-slate-400">Pilih filter yang diperlukan saja.</p></div><button type="button" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200" @click="advancedOpen=false" aria-label="Tutup filter"><svg class="block size-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M3.5 3.5l9 9M12.5 3.5l-9 9"/></svg></button></div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><label class="mb-1.5 block text-[11px] font-bold text-slate-600">Periode</label><select class="field text-sm" name="period" x-model="period"><option value="">Semua waktu</option><option value="today">Hari ini</option><option value="this_week">Minggu ini</option><option value="this_month">Bulan ini</option><option value="last_month">Bulan lalu</option><option value="this_year">Tahun ini</option><option value="custom">Rentang tanggal</option></select></div>
            <div x-show="period==='custom'" x-cloak><label class="mb-1.5 block text-[11px] font-bold text-slate-600">Dari tanggal</label><input type="date" class="field text-sm" name="date_from" value="{{ request('date_from') }}" :disabled="period!=='custom'"></div>
            <div x-show="period==='custom'" x-cloak><label class="mb-1.5 block text-[11px] font-bold text-slate-600">Sampai tanggal</label><input type="date" class="field text-sm" name="date_to" value="{{ request('date_to') }}" :disabled="period!=='custom'"></div>
            <div><label class="mb-1.5 block text-[11px] font-bold text-slate-600">Jenis aktivitas</label><select class="field text-sm" name="type"><option value="">Semua jenis aktivitas</option>@foreach(array_merge(\App\Models\Activity::ACTIVITY_TYPES, \App\Models\Activity::DECISION_TYPES) as $activityType)<option value="{{ $activityType }}" @selected(request('type')===$activityType)>{{ \App\Models\Activity::TYPES[$activityType] }}</option>@endforeach</select></div>
            <div><label class="mb-1.5 block text-[11px] font-bold text-slate-600">Akun / PIC</label>@if($activityUsers->count()>1)<select class="field text-sm" name="user_id"><option value="">Semua akun</option>@foreach($activityUsers as $account)<option value="{{ $account->id }}" @selected($selectedUserId===$account->id)>{{ $account->name }}</option>@endforeach</select>@else<div class="field flex h-[42px] items-center bg-slate-50 py-0 text-sm font-semibold text-slate-600"><span class="truncate">{{ ($activityUsers->first() ?? auth()->user())->name }}</span></div>@endif</div>
        </div>
        <div class="mt-4 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
            @if($canReviewEvidenceIntegrity)
                <label class="group flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 transition hover:border-brand-200 hover:bg-brand-50/40">
                    <input type="checkbox" name="needs_review" value="1" @checked($needsEvidenceReview) class="peer sr-only">
                    <span class="relative h-5 w-9 shrink-0 rounded-full bg-slate-300 transition peer-checked:bg-brand-600 after:absolute after:left-0.5 after:top-0.5 after:size-4 after:rounded-full after:bg-white after:shadow-sm after:transition peer-checked:after:translate-x-4"></span>
                    <span>
                        <span class="block text-[11px] font-bold text-slate-700">Bukti perlu ditinjau</span>
                        <span class="block text-[9px] text-slate-400">Tampilkan aktivitas dengan bukti mencurigakan</span>
                    </span>
                </label>
            @else
                <span></span>
            @endif
            <div class="flex gap-2 sm:min-w-64">
                <a href="{{ route('activities.index') }}" class="btn-secondary flex-1">Reset</a>
                <button class="btn-primary flex-1">Terapkan</button>
            </div>
        </div>
    </div>
</form>

<section class="card overflow-hidden">
    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
        <div class="flex items-center gap-2 text-xs"><b class="text-ink">{{ number_format($activities->total(),0,',','.') }} aktivitas</b><span class="text-slate-400">·</span><span class="text-slate-500">Data {{ $activities->firstItem()??0 }}–{{ $activities->lastItem()??0 }}</span></div>
        <div class="text-[10px] font-medium text-slate-500">Klik <b>Detail</b> untuk informasi lengkap</div>
    </header>

    <div class="scrollbar-kanban overflow-x-auto">
        <table class="w-full min-w-[1080px] table-fixed border-collapse text-left">
            <thead class="bg-slate-100">
                <tr class="border-b border-slate-300 text-[10px] font-extrabold uppercase tracking-wide text-slate-500">
                    <th class="w-[112px] border-r border-slate-200 px-3 py-3">Waktu</th>
                    <th class="w-[190px] border-r border-slate-200 px-3 py-3">Aktivitas</th>
                    <th class="w-[180px] border-r border-slate-200 px-3 py-3">Customer</th>
                    <th class="w-[190px] border-r border-slate-200 px-3 py-3">Hasil</th>
                    <th class="w-[145px] border-r border-slate-200 px-3 py-3">Follow-up</th>
                    <th class="w-[145px] border-r border-slate-200 px-3 py-3">PIC</th>
                    <th class="w-[86px] border-r border-slate-200 px-3 py-3 text-center">Bukti</th>
                    <th class="w-[78px] px-3 py-3 text-center">Aksi</th>
                </tr>
            </thead>

            @forelse($activities as $activity)
                @php
                    $typeStyle=in_array($activity->type,\App\Models\Activity::DECISION_TYPES,true)?'bg-violet-100 text-violet-700':'bg-sky-100 text-sky-700';
                    $firstEvidence=$activity->attachments->first();
                    $firstEvidenceIsHeic=$firstEvidence&&in_array(strtolower(pathinfo($firstEvidence->name,PATHINFO_EXTENSION)),['heic','heif'],true);
                    $firstEvidencePreviewPath=$firstEvidence?data_get($firstEvidence->evidence_metadata,'preview_path'):null;
                    $firstEvidencePreviewUrl=$firstEvidencePreviewPath?url('storage/'.ltrim($firstEvidencePreviewPath,'/')):null;
                    $firstEvidenceThumbnailPath=$firstEvidence?data_get($firstEvidence->evidence_metadata,'thumbnail_path'):null;
                    $firstEvidenceThumbnailUrl=$firstEvidenceThumbnailPath?url('storage/'.ltrim($firstEvidenceThumbnailPath,'/')):null;
                    $attentionCount=$canReviewEvidenceIntegrity
                        ? $activity->attachments->whereIn('verification_status',['duplicate','suspicious','warning','review','tampered','ai_suspected','ai_review'])->count()
                        : 0;
                    $followUpPending=$activity->next_follow_up_at&&!$activity->follow_up_completed_at;
                    $followUpOverdue=$followUpPending&&$activity->next_follow_up_at->isPast();
                @endphp
                <tbody id="activity-{{ $activity->id }}" data-activity-evidence-scope x-data="{ modalOpen: @js((int) request('activity') === $activity->id), inviteOpen: false, openModal() { this.modalOpen = true; document.body.style.overflow = 'hidden' }, closeModal() { this.modalOpen = false; this.inviteOpen = false; document.body.style.overflow = '' } }" x-init="if (modalOpen) document.body.style.overflow = 'hidden'" class="border-b border-slate-200 last:border-b-0">
                    <tr class="activity-row group transition odd:bg-white hover:bg-indigo-50/50" :class="modalOpen&&'bg-indigo-50/60'">
                        <td class="border-r border-slate-100 px-3 py-3 align-top"><div class="whitespace-nowrap text-[11px] font-bold text-slate-800">{{ $activity->occurred_at->translatedFormat('d M Y') }}</div><div class="mt-0.5 text-[10px] text-slate-400">{{ $activity->occurred_at->format('H:i') }}</div></td>
                        <td class="border-r border-slate-100 px-3 py-3 align-top"><div class="truncate text-[12px] font-bold text-ink" title="{{ $activity->summary }}">{{ $activity->summary }}</div><div class="mt-1.5 flex gap-1"><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $typeStyle }}">{{ \App\Models\Activity::TYPES[$activity->type]??$activity->type }}</span></div></td>
                        <td class="border-r border-slate-100 px-3 py-3 align-top"><div class="truncate text-[12px] font-bold text-slate-800" title="{{ $activity->customer->company_name }}">{{ $activity->customer->company_name }}</div>@if($activity->opportunity)<div class="mt-1 truncate text-[10px] text-brand-600" title="{{ $activity->opportunity->title }}">{{ $activity->opportunity->title }}</div>@else<div class="mt-1 text-[10px] text-slate-400">Aktivitas umum</div>@endif</td>
                        <td class="border-r border-slate-100 px-3 py-3 align-top"><p class="line-clamp-2 text-[11px] leading-relaxed {{ $activity->result?'text-slate-700':'italic text-slate-400' }}">{{ $activity->result?:($activity->detail?:'Belum ada hasil') }}</p></td>
                        <td class="border-r border-slate-100 px-3 py-3 align-top">@if($activity->next_follow_up_at)<div class="text-[11px] font-bold {{ $followUpOverdue?'text-rose-700':'text-slate-700' }}">{{ $activity->next_follow_up_at->translatedFormat('d M Y') }}</div><div class="mt-0.5 text-[10px] {{ $followUpOverdue?'font-bold text-rose-600':($activity->follow_up_completed_at?'font-bold text-emerald-600':'text-slate-400') }}">{{ $activity->next_follow_up_at->format('H:i') }} · {{ $followUpOverdue?'Terlambat':($activity->follow_up_completed_at?'Selesai':'Terjadwal') }}</div>@if($followUpPending)<a href="{{ route('activities.follow-up',$activity) }}" class="mt-1 inline-block text-[10px] font-bold text-brand-600">Kerjakan →</a>@endif @else<span class="text-[10px] text-slate-400">Tidak dijadwalkan</span>@endif</td>
                        <td class="border-r border-slate-100 px-3 py-3 align-top"><div class="flex items-center gap-2"><span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-[8px] font-black text-brand-700">{{ collect(explode(' ',$activity->user->name))->take(2)->map(fn($word)=>mb_substr($word,0,1))->join('') }}</span><div class="min-w-0"><div class="truncate text-[11px] font-bold text-slate-800">{{ $activity->user->name }}</div><div class="text-[9px] text-slate-400">{{ $activity->user->employee_id?:'Akun CRM' }}</div></div></div></td>
                        <td class="border-r border-slate-100 px-3 py-3 text-center align-top">@if($firstEvidence)<div class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 shadow-sm">@if($firstEvidenceThumbnailUrl)<img src="{{ $firstEvidenceThumbnailUrl }}" alt="" class="size-8 rounded-md object-cover" loading="lazy" decoding="async" fetchpriority="low" onerror="this.remove()">@elseif($firstEvidencePreviewUrl)<img src="{{ $firstEvidencePreviewUrl }}" alt="" class="size-8 rounded-md object-cover" loading="lazy" decoding="async" fetchpriority="low" onerror="this.remove()">@elseif(str_starts_with($firstEvidence->mime_type??'','image/')&&!$firstEvidenceIsHeic)<img src="{{ url('storage/'.ltrim($firstEvidence->path,'/')) }}" alt="" class="size-8 rounded-md object-cover" loading="lazy" decoding="async" fetchpriority="low" onerror="this.remove()">@elseif($firstEvidenceIsHeic)<span data-heic-preview="{{ url('storage/'.ltrim($firstEvidence->path,'/')) }}" data-heic-alt="{{ $firstEvidence->name }}" data-heic-class="size-8 rounded-md object-cover" class="grid size-8 place-items-center overflow-hidden rounded-md bg-sky-50 text-[7px] font-black text-sky-600">HEIC</span>@else<span class="grid size-8 place-items-center rounded-md bg-rose-50 text-[8px] font-black text-rose-600">PDF</span>@endif<span class="pr-1 text-[10px] font-bold text-slate-600">{{ $activity->attachments->count() }}</span></div>@if($attentionCount)<div class="mt-1 text-[9px] font-bold text-amber-600">Perlu dicek</div>@endif @else<span class="text-[10px] text-slate-300">—</span>@endif</td>
                        <td class="px-3 py-3 text-center align-top"><button type="button" data-preload-activity-evidence class="inline-flex whitespace-nowrap rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-slate-600 shadow-sm hover:text-brand-700" @click="openModal()">Detail</button></td>
                    </tr>

                    <template x-teleport="body">
                        <div x-show="modalOpen" x-cloak x-transition.opacity @keydown.escape.window="closeModal()" @click.self="closeModal()" class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/55 p-3 backdrop-blur-sm sm:p-6">
                            <div x-show="modalOpen" x-transition class="flex max-h-[92vh] w-full max-w-7xl flex-col overflow-hidden rounded-2xl bg-slate-50 shadow-2xl">
                                <header class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4">
                                    <div><h4 class="text-sm font-extrabold text-ink">Rincian aktivitas</h4><p class="mt-1 text-[11px] text-slate-500">Semua informasi yang dicatat oleh {{ $activity->user->name }}</p></div>
                                    <div class="flex items-center gap-3"><span class="rounded-lg bg-slate-100 px-3 py-2 text-[11px] font-bold text-slate-500">#ACT-{{ str_pad($activity->id,5,'0',STR_PAD_LEFT) }}</span><button type="button" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-rose-100 hover:text-rose-600" @click="closeModal()" aria-label="Tutup modal"><svg width="12" height="12" class="block" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button></div>
                                </header>
                                <div class="overflow-y-auto p-5">
                            <div class="grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(420px,.9fr)]">
<div><div class="overflow-hidden rounded-xl border border-slate-200 bg-white"><table class="w-full text-left text-[13px]"><tbody class="divide-y divide-slate-100"><tr><th class="w-44 bg-slate-50 px-4 py-3 font-bold text-slate-600">Judul / ringkasan</th><td class="px-4 py-3 font-semibold text-slate-800">{{ $activity->summary }}</td></tr><tr><th class="bg-slate-50 px-4 py-3 font-bold text-slate-600">Catatan aktivitas</th><td class="whitespace-pre-line px-4 py-3 leading-relaxed text-slate-700">{{ $activity->detail?:'Tidak diisi' }}</td></tr><tr class="bg-emerald-50/60"><th class="px-4 py-3 font-bold text-emerald-700">Hasil / keputusan</th><td class="whitespace-pre-line px-4 py-3 font-semibold leading-relaxed text-slate-800">{{ $activity->result ?: (in_array($activity->type, \App\Models\Activity::DECISION_TYPES, true) ? 'Menunggu keputusan approver' : 'Tidak diisi') }}</td></tr><tr><th class="bg-slate-50 px-4 py-3 font-bold text-slate-600">Next action</th><td class="whitespace-pre-line px-4 py-3 text-slate-700">{{ $activity->next_action?:'Tidak diisi' }}</td></tr><tr><th class="bg-slate-50 px-4 py-3 font-bold text-slate-600">Jadwal follow-up</th><td class="px-4 py-3 font-semibold text-slate-700">{{ $activity->next_follow_up_at?->translatedFormat('d M Y, H:i')??'Tidak dijadwalkan' }}</td></tr><tr><th class="bg-slate-50 px-4 py-3 font-bold text-slate-600">Jenis aktivitas</th><td class="px-4 py-3 text-slate-700">{{ \App\Models\Activity::TYPES[$activity->type]??$activity->type }}</td></tr><tr><th class="bg-slate-50 px-4 py-3 font-bold text-slate-600">Opportunity</th><td class="px-4 py-3 text-slate-700">{{ $activity->opportunity?->title??'Aktivitas umum · tanpa opportunity' }}</td></tr></tbody></table></div>@include('activities._approval_details', ['activity' => $activity])</div>
                                <div><div class="mb-3 flex items-center justify-between"><h4 class="text-[12px] font-extrabold uppercase tracking-wide text-slate-600">Bukti & tracking</h4><span class="text-[11px] text-slate-500">{{ $activity->attachments->count() }} lampiran</span></div>@if($activity->attachments->isNotEmpty())@include('activities._attachments',['activity'=>$activity,'evidenceInitiallyOpen'=>true,'inlineEvidenceThumbnails'=>true])@else<div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Tidak ada bukti yang diunggah.</div>@endif</div>
                            </div>
                            <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3">
                                    <div><h4 class="text-[12px] font-extrabold text-ink">Diskusi aktivitas</h4><p class="mt-0.5 text-[10px] text-slate-400">Diskusikan aktivitas ini bersama rekan yang memiliki akses.</p></div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center">
                                            <div class="flex -space-x-1.5">
                                                <span class="grid size-7 place-items-center rounded-full border-2 border-white bg-brand-100 text-[8px] font-black text-brand-700" title="{{ $activity->user->name }}">{{ mb_substr($activity->user->name, 0, 1) }}</span>
                                                @foreach(collect($activity->participants ?? [])->take(4) as $participantId)
                                                    @if($participantUsers->get($participantId))
                                                        <span class="grid size-7 place-items-center rounded-full border-2 border-white bg-sky-100 text-[8px] font-black text-sky-700" title="{{ $participantUsers->get($participantId)->name }}">{{ mb_substr($participantUsers->get($participantId)->name, 0, 1) }}</span>
                                                    @endif
                                                @endforeach
                                            </div>
                                            @if(count($activity->participants ?? []) > 4)<span class="ml-1 text-[10px] font-bold text-slate-500">+{{ count($activity->participants)-4 }}</span>@endif
                                        </div>
                                        <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-[10px] font-bold text-slate-600 shadow-sm transition hover:border-brand-200 hover:text-brand-700" @click="inviteOpen = true">
                                            <svg width="11" height="11" class="block shrink-0" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 3v10M3 8h10"/></svg><span>Tambah orang</span>
                                        </button>
                                        @php
                                            $uninvitedUsers = $activityDiscussionUsers->reject(fn ($user) =>
                                                (int) $user->id === (int) $activity->user_id ||
                                                in_array((int) $user->id, array_map('intval', $activity->participants ?? []), true)
                                            );
                                        @endphp
                                        <template x-teleport="body">
                                            <div x-show="inviteOpen" x-cloak x-transition.opacity @keydown.escape.window="inviteOpen = false" @click.self="inviteOpen = false" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-[2px]">
                                                <form x-show="inviteOpen" x-transition x-data="{ selected: [], search: '' }" method="POST" action="{{ route('activities.participants.store', $activity) }}" class="flex h-[520px] max-h-[78vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                                                    @csrf
                                                    <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                                                        <div>
                                                            <h5 class="text-sm font-extrabold text-ink">Tambah orang ke diskusi</h5>
                                                            <p class="mt-1 max-w-sm text-[11px] leading-relaxed text-slate-500">Pilih rekan yang perlu melihat aktivitas dan ikut berdiskusi.</p>
                                                        </div>
                                                        <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" @click="inviteOpen = false" aria-label="Tutup"><svg width="11" height="11" class="block" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button>
                                                    </header>

                                                    @if($uninvitedUsers->isNotEmpty())
                                                        <div class="shrink-0 border-b border-slate-100 px-4 py-3">
                                                            <div class="relative">
                                                                <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                                                                <input x-model.debounce.150ms="search" type="text" class="field h-10 bg-slate-50 pl-9 pr-9 text-sm" placeholder="Cari nama atau ID akun..." autocomplete="off">
                                                                <button x-show="search" type="button" class="absolute right-2.5 top-1/2 grid size-6 -translate-y-1/2 place-items-center rounded-full text-slate-400 hover:bg-slate-200 hover:text-slate-600" @click="search = ''" aria-label="Hapus pencarian"><svg width="9" height="9" class="block" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <div class="min-h-0 flex-1 overflow-y-auto p-3">
                                                        @forelse($uninvitedUsers as $discussionUser)
                                                            <label x-show="@js(mb_strtolower($discussionUser->name.' '.($discussionUser->employee_id ?? ''))).includes(search.trim().toLowerCase())" class="mb-1 flex cursor-pointer items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 transition hover:border-slate-200 hover:bg-slate-50 has-[:checked]:border-brand-200 has-[:checked]:bg-brand-50/60">
                                                                <input x-model="selected" type="checkbox" name="participant_ids[]" value="{{ $discussionUser->id }}" class="size-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sky-100 text-[10px] font-black text-sky-700">{{ collect(preg_split('/\s+/', trim($discussionUser->name)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join('') }}</span>
                                                                <span class="min-w-0 flex-1">
                                                                    <span class="block truncate text-[13px] font-bold text-slate-800">{{ $discussionUser->name }}</span>
                                                                    <span class="mt-0.5 block truncate text-[10px] text-slate-400">{{ $discussionUser->employee_id ?: 'Akun CRM' }}</span>
                                                                </span>
                                                                <span x-show="selected.includes('{{ $discussionUser->id }}')" class="text-[10px] font-bold text-brand-600">Dipilih</span>
                                                            </label>
                                                        @empty
                                                            <div class="px-4 py-12 text-center">
                                                                <div class="mx-auto grid size-11 place-items-center rounded-full bg-emerald-50 text-lg text-emerald-600">✓</div>
                                                                <div class="mt-3 text-sm font-bold text-slate-700">Semua orang sudah dilibatkan</div>
                                                                <p class="mt-1 text-[11px] text-slate-400">Tidak ada akun aktif lain yang dapat ditambahkan.</p>
                                                            </div>
                                                        @endforelse
                                                    </div>

                                                    <footer class="flex shrink-0 items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-5 py-3.5">
                                                        @if($uninvitedUsers->isNotEmpty())
                                                            <span class="text-[11px] font-medium text-slate-500"><b x-text="selected.length">0</b> orang dipilih</span>
                                                            <div class="flex items-center gap-2">
                                                                <button type="button" class="btn-secondary h-9 px-4 text-xs" @click="inviteOpen = false">Batal</button>
                                                                <button class="btn-primary h-9 px-4 text-xs disabled:cursor-not-allowed disabled:opacity-50" :disabled="selected.length === 0">Tambahkan</button>
                                                            </div>
                                                        @else
                                                            <span></span><button type="button" class="btn-secondary h-9 px-4 text-xs" @click="inviteOpen = false">Tutup</button>
                                                        @endif
                                                    </footer>
                                                </form>
                                            </div>
                                        </template>
                                    </div>
                                </header>
                                <div data-room-chat data-room-messages-url="{{ route('activities.comments', $activity) }}" data-current-user="{{ auth()->id() }}" data-last-message-id="{{ $activity->comments->max('id') ?? 0 }}">
                                    <div data-room-message-list class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                                        @forelse($activity->comments as $comment)
                                            <article data-message-id="{{ $comment->id }}" class="flex gap-3 p-4 {{ $comment->user_id === auth()->id() ? 'bg-indigo-50/30' : '' }}">
                                                <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-100 text-xs font-extrabold text-brand-700">{{ mb_substr($comment->user->name, 0, 1) }}</div>
                                                <div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-3"><div class="text-sm font-bold text-ink">{{ $comment->user->name }}</div><time class="text-[10px] text-slate-400">{{ $comment->created_at->format('d M Y, H:i') }}</time></div><p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-600">{{ $comment->body }}</p></div>
                                            </article>
                                        @empty
                                            <div data-room-empty class="p-8 text-center text-xs text-slate-400">Belum ada komentar. Mulai diskusi untuk aktivitas ini.</div>
                                        @endforelse
                                    </div>
                                    <form data-room-message-form method="POST" action="{{ route('activities.comments.store', $activity) }}" class="border-t border-slate-100 bg-slate-50/70 p-4">
                                        @csrf
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end"><textarea class="field min-h-12 flex-1 resize-none bg-white" rows="2" name="body" placeholder="Tulis komentar untuk rekan yang dilibatkan..." required></textarea><button class="btn-primary shrink-0">Kirim komentar</button></div>
                                        <p data-room-send-status class="mt-2 hidden text-[10px] text-slate-400"></p>
                                    </form>
                                </div>
                            </section>
                                </div>
                            </div>
                        </div>
                    </template>
                </tbody>
            @empty
                <tbody>
                    <tr>
                        <td colspan="8" class="p-0">
                            <div class="flex min-h-56 flex-col items-center justify-center px-6 py-12 text-center">
                                <div class="grid size-11 place-items-center rounded-full bg-slate-100 text-slate-400">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M8.5 11h5"/></svg>
                                </div>
                                <div class="mt-3 text-sm font-bold text-slate-700">{{ request()->hasAny(['search','type','user_id']) ? 'Aktivitas tidak ditemukan' : 'Belum ada aktivitas' }}</div>
                                <p class="mt-1 text-xs text-slate-400">{{ request()->hasAny(['search','type','user_id']) ? 'Coba ubah pilihan filter atau tampilkan kembali semua aktivitas.' : 'Catat aktivitas pertama untuk mulai membangun riwayat customer.' }}</p>
                                @if(request()->hasAny(['search','type','user_id']))
                                    <a href="{{ route('activities.index') }}" class="btn-secondary mt-4 h-9 px-4 text-xs">Tampilkan semua aktivitas</a>
                                @else
                                    <a href="{{ route('activities.create') }}" class="btn-primary mt-4 h-9 px-4 text-xs">+ Catat aktivitas</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                </tbody>
            @endforelse
        </table>
    </div>
</section>

<div class="mt-5">{{ $activities->links() }}</div>
@endsection
