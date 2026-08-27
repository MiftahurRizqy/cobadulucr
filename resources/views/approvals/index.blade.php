@extends('layouts.app')
@section('title','Approval')
@section('eyebrow','Workspace · Approval')
@section('content')
@php
    $statusOptions = ['pending' => 'Pending', 'revision' => 'Revision Required', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All Statuses'];
@endphp

<div class="space-y-4">
    <section class="grid gap-3 sm:grid-cols-4">
        @foreach(['pending' => ['Pending', 'bg-amber-500'], 'revision' => ['Revision Required', 'bg-sky-500'], 'approved' => ['Approved', 'bg-emerald-500'], 'rejected' => ['Rejected', 'bg-rose-500']] as $key => [$label, $colorClass])
            <a href="{{ route('approvals.index', ['status' => $key]) }}" class="card flex items-center justify-between p-4 {{ $status === $key ? 'ring-2 ring-brand-500' : '' }}">
                <div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</div><div class="mt-1 text-2xl font-extrabold text-ink">{{ $counts[$key] ?? 0 }}</div></div>
                <span class="size-2.5 rounded-full {{ $colorClass }}"></span>
            </a>
        @endforeach
    </section>

    <form class="card grid gap-3 p-4 md:grid-cols-[1fr_220px_200px_auto]">
        <input class="field" name="search" value="{{ request('search') }}" placeholder="Cari pengajuan, customer, atau pengaju...">
        <select class="field" name="type"><option value="">Semua jenis approval</option>@foreach(\App\Models\Activity::DECISION_TYPES as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ \App\Models\Activity::TYPES[$type] }}</option>@endforeach</select>
        <select class="field" name="status">@foreach($statusOptions as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach</select>
        <button class="btn-primary">Terapkan</button>
    </form>

    <section class="card overflow-hidden">
        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">{{ $approvals->total() }} pengajuan</h3><p class="mt-1 text-[10px] text-slate-400">Periksa detail pengajuan sebelum memberikan keputusan.</p></div></header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] text-left">
                <thead class="bg-slate-50 text-[9px] font-black uppercase tracking-wide text-slate-400"><tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Pengajuan</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Pengaju</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-center">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($approvals as $approval)
                    @php
                        $activity = $approval->activity;
                        $statusLabels = ['pending'=>'Pending','approved'=>'Approved','revision'=>'Revision Required','rejected'=>'Rejected'];
                        $statusStyles = ['pending'=>'bg-amber-50 text-amber-700','approved'=>'bg-emerald-50 text-emerald-700','revision'=>'bg-sky-50 text-sky-700','rejected'=>'bg-rose-50 text-rose-700'];
                    @endphp
                    <tr id="approval-{{ $activity->id }}" x-data="{ open: @js((int) request('activity') === (int) $activity->id) }" class="hover:bg-slate-50/60">
                        <td class="px-4 py-3"><div class="text-xs font-bold text-ink">{{ $activity->created_at->translatedFormat('d M Y') }}</div><div class="mt-1 text-[10px] text-slate-400">{{ $activity->created_at->format('H:i') }}</div></td>
                        <td class="px-4 py-3"><div class="text-xs font-extrabold text-ink">{{ \App\Models\Activity::TYPES[$activity->type] }}</div><div class="mt-1 max-w-xs truncate text-[10px] text-slate-500">{{ $activity->summary }}</div></td>
                        <td class="px-4 py-3 text-xs font-bold text-slate-700">{{ $activity->customer->company_name }}</td>
                        <td class="px-4 py-3"><div class="text-xs font-bold text-slate-700">{{ $activity->user->name }}</div><div class="mt-1 text-[9px] text-slate-400">{{ $activity->user->employee_id }}</div></td>
                        <td class="px-4 py-3"><span class="badge {{ $statusStyles[$approval->approval_status] }}">{{ $statusLabels[$approval->approval_status] }}</span></td>
                        <td class="px-4 py-3 text-center"><button type="button" class="btn-secondary h-8 px-3 text-[10px]" @click="open=true">Detail</button>
                            <template x-teleport="body"><div x-show="open" x-cloak @keydown.escape.window="open=false" @click.self="open=false" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-3 backdrop-blur-sm sm:p-6"><div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-slate-50 shadow-2xl"><header class="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4"><div><h3 class="text-sm font-extrabold text-ink">{{ \App\Models\Activity::TYPES[$activity->type] }}</h3><p class="mt-1 text-[10px] text-slate-400">{{ $activity->customer->company_name }} · diajukan {{ $activity->user->name }}</p></div><button type="button" class="grid size-10 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700" @click="open=false" aria-label="Tutup modal"><svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button></header><div class="overflow-y-auto p-5"><div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-[9px] font-bold uppercase text-slate-400">Ringkasan</div><div class="mt-1 text-sm font-bold text-ink">{{ $activity->summary }}</div>@if($activity->detail)<div class="mt-3 whitespace-pre-line text-xs leading-relaxed text-slate-600">{{ $activity->detail }}</div>@endif</div>@if($activity->opportunity)<div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-100 bg-brand-50/60 p-4"><div class="min-w-0"><div class="text-[9px] font-bold uppercase tracking-wide text-brand-500">Opportunity terkait</div><div class="mt-1 text-[10px] font-bold text-brand-600">{{ $activity->opportunity->opportunity_id }}</div><div class="mt-0.5 truncate text-xs font-extrabold text-slate-800">{{ $activity->opportunity->title }}</div></div><a href="{{ route('opportunities.show', $activity->opportunity) }}" class="inline-flex h-8 shrink-0 items-center rounded-lg border border-brand-200 bg-white px-3 text-[10px] font-extrabold text-brand-600 shadow-sm transition hover:border-brand-300 hover:bg-brand-50">View Opportunity &rarr;</a></div>@endif
                            @include('activities._approval_details', ['activity'=>$activity])</div></div></div></template>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-12 text-center text-sm text-slate-400">Tidak ada pengajuan pada status ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($approvals->hasPages())<footer class="border-t border-slate-100 px-5 py-4">{{ $approvals->links() }}</footer>@endif
    </section>
</div>
@endsection
