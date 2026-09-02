<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm">
    <div class="flex min-w-0 flex-1 gap-1">
        @if(auth()->user()->canAccess('approvals.view'))
        <a href="{{ route('approvals.index') }}" class="flex min-w-[130px] items-center justify-between gap-3 rounded-lg px-4 py-2.5 text-xs font-bold transition {{ request()->routeIs('approvals.*') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}">
            <span>Approval</span><span class="rounded-full px-2 py-0.5 text-[9px] {{ request()->routeIs('approvals.*') ? 'bg-white/20 text-white' : 'bg-amber-50 text-amber-700' }}">{{ $approvalWaiting ?? 0 }}</span>
        </a>
        @endif
        @if(auth()->user()->canAccess('tasks.view'))
        <a href="{{ route('tasks.index') }}" class="flex min-w-[130px] items-center justify-between gap-3 rounded-lg px-4 py-2.5 text-xs font-bold transition {{ request()->routeIs('tasks.*') ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}">
            <span>Task</span><span class="rounded-full px-2 py-0.5 text-[9px] {{ request()->routeIs('tasks.*') ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $taskOpen ?? 0 }}</span>
        </a>
        @endif
    </div>
    <div class="hidden pr-3 text-[10px] font-semibold text-slate-400 sm:block">Pusat approval dan task</div>
</div>
