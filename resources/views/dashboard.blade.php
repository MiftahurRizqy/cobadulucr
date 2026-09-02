@extends('layouts.app')
@section('title','Ringkasan Kerja')
@section('eyebrow', (auth()->user()->roleNames() ?: ucfirst(str_replace('_',' ',auth()->user()->authority_level))).' · Dashboard')
@section('content')
<div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div><h2 class="text-lg font-extrabold tracking-tight text-ink">Selamat datang, {{ str(auth()->user()->name)->before(' ') }}</h2><p class="mt-1 text-xs text-slate-500">Fokus hari ini, kondisi pipeline, dan aktivitas terbaru dalam satu halaman.</p></div>
    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-500">{{ now()->translatedFormat('l, d F Y') }}</div>
</div>

<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
@foreach([
    ['Nilai pipeline','Rp '.number_format($stats['pipeline'],0,',','.'),$stats['open_opportunities'].' opportunity aktif','bg-indigo-50 text-indigo-600','chart'],
    ['Weighted pipeline','Rp '.number_format($stats['weighted'],0,',','.'),'Nilai sesuai probabilitas','bg-emerald-50 text-emerald-600','trend'],
    ['Customer',$stats['customers'],'Customer dalam akses Anda','bg-sky-50 text-sky-600','customer'],
    ['Task jatuh tempo',$stats['tasks_due'],$stats['tasks_overdue'].' sudah terlambat','bg-amber-50 text-amber-600','task'],
] as [$label,$value,$hint,$color,$icon])
<div class="metric">
    <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="text-[10px] font-bold text-slate-500">{{ $label }}</div><div class="mt-2 truncate text-xl font-extrabold tracking-tight text-ink">{{ $value }}</div></div><div class="grid size-9 shrink-0 place-items-center rounded-lg {{ $color }}">
        @if($icon==='trend')<span class="text-base">↗</span>@elseif($icon==='customer')<span class="text-[10px] font-black">CRM</span>@elseif($icon==='task')<span class="text-base">✓</span>@else<span class="text-base">▥</span>@endif
    </div></div>
    <div class="mt-3 flex items-center gap-1.5 text-[10px] {{ $icon==='task' && $stats['tasks_overdue'] ? 'font-bold text-rose-500' : 'text-slate-400' }}"><span class="size-1.5 rounded-full bg-current opacity-70"></span>{{ $hint }}</div>
</div>
@endforeach
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
    <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Pipeline berdasarkan tahap</h3><p class="mt-1 text-[10px] text-slate-400">Jumlah dan nilai opportunity aktif</p></div>@if(auth()->user()->canAccess('opportunities.view'))<a href="{{ route('opportunities.kanban') }}" class="text-[11px] font-bold text-brand-600 hover:text-brand-700">Buka kanban →</a>@endif</div>
        <div class="p-5">
            @php($maxStage = max(1,$stageSummary->max('opportunities_count') ?? 1))
            <div class="space-y-4">
                @forelse($stageSummary as $stage)
                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-3 text-[11px]"><span class="min-w-0 truncate font-bold text-slate-600">{{ $stage->name }} <span class="font-normal text-slate-400">({{ $stage->opportunities_count }})</span></span><span class="shrink-0 font-extrabold text-slate-600">Rp {{ number_format($stage->open_value ?? 0,0,',','.') }}</span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full transition-all" style="width:{{ max(4,($stage->opportunities_count/$maxStage)*100) }}%;background:{{ $stage->color }}"></div></div>
                </div>
                @empty<div class="empty-state">Belum ada opportunity aktif.</div>@endforelse
            </div>
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Task prioritas</h3><p class="mt-1 text-[10px] text-slate-400">Urut dari batas waktu terdekat</p></div>@if(auth()->user()->canAccess('tasks.view'))<a href="{{ route('tasks.index') }}" class="text-[11px] font-bold text-brand-600">Lihat semua →</a>@endif</div>
        <div class="divide-y divide-slate-100">
        @forelse($myTasks as $task)
            <div class="group flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50/70"><span class="grid size-5 shrink-0 place-items-center rounded-md border {{ $task->due_at?->isPast() ? 'border-rose-200 bg-rose-50 text-rose-500' : 'border-slate-200 bg-white text-slate-300' }} text-[10px]">✓</span><div class="min-w-0 flex-1"><div class="truncate text-xs font-bold text-ink">{{ $task->title }}</div><div class="mt-1 truncate text-[10px] text-slate-400">{{ $task->customer?->company_name ?? 'Task internal' }}</div></div><div class="text-right"><div class="text-[10px] font-bold {{ $task->due_at?->isPast() ? 'text-rose-500' : 'text-slate-500' }}">{{ $task->due_at?->format('d M') ?? 'Tanpa batas waktu' }}</div><span class="mt-1 inline-block text-[9px] capitalize text-slate-400">{{ $task->priority }}</span></div></div>
        @empty<div class="empty-state"><div class="mb-2 text-2xl">✓</div>Semua task sudah terkendali.</div>@endforelse
        </div>
    </section>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-2">
    <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Follow-up customer</h3><p class="mt-1 text-[10px] text-slate-400">Jadwal yang perlu segera ditindaklanjuti</p></div>@if(auth()->user()->canAccess('customers.view'))<a href="{{ route('customers.index') }}" class="text-[11px] font-bold text-brand-600">Semua customer →</a>@endif</div>
        <div class="divide-y divide-slate-100">@forelse($followUps as $customer)<a href="{{ route('customers.show',$customer) }}" class="flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50"><div class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-[10px] font-extrabold text-brand-600">{{ $customer->initials }}</div><div class="min-w-0 flex-1"><div class="truncate text-xs font-bold text-ink">{{ $customer->company_name }}</div><div class="mt-1 truncate text-[10px] text-slate-400">{{ $customer->next_follow_up_at?->translatedFormat('d M Y, H:i') }}</div></div><span class="badge {{ $customer->status==='risky' ? 'bg-rose-50 text-rose-600' : ($customer->status==='pareto' ? 'bg-violet-50 text-violet-600' : ($customer->status==='inactive' ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-600')) }}">{{ ['pareto'=>'Cust Pareto','active'=>'Cust Aktif','inactive'=>'Cust Non Aktif','risky'=>'Cust Risky'][$customer->status] ?? ucfirst($customer->status) }}</span></a>@empty<div class="empty-state">Belum ada jadwal follow-up.</div>@endforelse</div>
    </section>
    <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Aktivitas terbaru</h3><p class="mt-1 text-[10px] text-slate-400">Pembaruan terakhir dari tim</p></div>@if(auth()->user()->canAccess('activities.view'))<a href="{{ route('activities.index') }}" class="text-[11px] font-bold text-brand-600">Lihat semua →</a>@endif</div>
        <div class="divide-y divide-slate-100">@forelse($recentActivities as $activity)<div class="flex items-center gap-3 px-5 py-3.5"><div class="grid size-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-[10px] font-black uppercase text-slate-500">{{ mb_substr($activity->type,0,2) }}</div><div class="min-w-0 flex-1"><div class="truncate text-xs font-bold text-ink">{{ $activity->summary }}</div><div class="mt-1 truncate text-[10px] text-slate-400">{{ $activity->customer->company_name }} · {{ $activity->user->name }}</div></div><span class="shrink-0 text-[9px] font-semibold text-slate-400">{{ $activity->occurred_at->diffForHumans(null,true) }}</span></div>@empty<div class="empty-state">Belum ada aktivitas.</div>@endforelse</div>
    </section>
</div>
@endsection
