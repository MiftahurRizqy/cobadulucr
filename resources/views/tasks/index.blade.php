@extends('layouts.app')
@section('title', 'Approval & Task')
@section('eyebrow', 'Workspace / Approval & Task')

@section('content')
    @include('work._tabs')
    <div class="mb-5 flex items-center justify-between gap-4">
        <div>
            <p class="text-xs text-slate-500">Pantau pekerjaan, pelaksana, dan batas waktunya.</p>
        </div>
        <a href="{{ route('tasks.create') }}" class="btn-primary shrink-0">+ Task baru</a>
    </div>

    <form method="GET" class="card mb-5 p-3">
        <div class="grid gap-2 lg:grid-cols-[minmax(260px,1fr)_180px_160px_170px_auto_auto]">
            <label class="relative block">
                <span class="sr-only">Cari task</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="9" cy="9" r="5.5"/><path d="m13 13 4 4" stroke-linecap="round"/>
                </svg>
                <input class="field !pl-10" name="q" value="{{ request('q') }}" placeholder="Cari judul, catatan, atau customer...">
            </label>

            <select class="field" name="status">
                <option value="">Semua status</option>
                @foreach (\App\Support\Crm::TASK_STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <select class="field" name="priority">
                <option value="">Semua prioritas</option>
                @foreach (\App\Support\Crm::PRIORITIES as $key => $label)
                    <option value="{{ $key }}" @selected(request('priority') === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <select class="field" name="scope">
                <option value="">Semua jenis task</option>
                <option value="customer" @selected(request('scope') === 'customer')>Terkait customer</option>
                <option value="internal" @selected(request('scope') === 'internal')>Internal</option>
            </select>

            <label class="btn-secondary min-h-11 cursor-pointer whitespace-nowrap px-4">
                <input type="checkbox" class="size-4 rounded border-slate-300 text-primary focus:ring-primary" name="mine" value="1" @checked(request()->boolean('mine'))>
                Task saya
            </label>

            <button class="btn-primary min-h-11 px-5">Terapkan</button>
        </div>

        @if (request()->hasAny(['q', 'status', 'priority', 'scope', 'mine']))
            <div class="mt-2 flex justify-end">
                <a href="{{ route('tasks.index') }}" class="text-[11px] font-semibold text-slate-500 transition hover:text-primary">Reset filter</a>
            </div>
        @endif
    </form>

    <section class="card overflow-hidden">
        <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div class="flex items-center gap-2">
                <strong class="text-sm text-ink">{{ $tasks->total() }} task</strong>
                @if ($tasks->total())
                    <span class="text-xs text-slate-400">· Menampilkan {{ $tasks->firstItem() }}–{{ $tasks->lastItem() }}</span>
                @endif
            </div>
            <span class="hidden text-[11px] text-slate-400 md:inline">20 data per halaman</span>
        </header>

        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[1120px] table-fixed text-left">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-[27%] px-5 py-3">Task</th>
                        <th class="w-[18%] px-4 py-3">Kaitan</th>
                        <th class="w-[15%] px-4 py-3">Dikerjakan oleh</th>
                        <th class="w-[14%] px-4 py-3">Batas waktu</th>
                        <th class="w-[10%] px-4 py-3">Prioritas</th>
                        <th class="w-[16%] px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tasks as $task)
                        @php
                            $isOverdue = $task->due_at?->isPast() && ! in_array($task->status, ['done', 'cancelled'], true);
                            $priorityClass = match ($task->priority) {
                                'urgent' => 'bg-rose-50 text-rose-600',
                                'high' => 'bg-amber-50 text-amber-700',
                                'medium' => 'bg-sky-50 text-sky-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                            $statusClass = match ($task->status) {
                                'done' => 'bg-emerald-50 text-emerald-700',
                                'blocked', 'cancelled' => 'bg-rose-50 text-rose-600',
                                'review' => 'bg-violet-50 text-violet-700',
                                'in_progress' => 'bg-sky-50 text-sky-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <tr class="align-middle transition hover:bg-slate-50/60">
                            <td class="px-5 py-4">
                                <div class="truncate text-sm font-bold text-ink" title="{{ $task->title }}">{{ $task->title }}</div>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="text-[10px] font-semibold text-primary">{{ $task->task_id }}</span>
                                    @if ($task->description)
                                        <span class="truncate text-[11px] text-slate-400" title="{{ $task->description }}">{{ $task->description }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="truncate text-xs font-semibold text-ink">{{ $task->customer?->company_name ?? 'Internal' }}</div>
                                <div class="mt-1 truncate text-[10px] text-slate-400">{{ $task->opportunity?->title ?? ($task->customer ? 'Tanpa opportunity' : 'Task internal') }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center">
                                    <div class="flex -space-x-2">
                                        @foreach ($task->assignees->take(3) as $user)
                                            <span title="{{ $user->name }}" class="grid size-7 place-items-center rounded-full border-2 border-white bg-indigo-50 text-[9px] font-bold text-primary">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                        @endforeach
                                    </div>
                                    @if ($task->assignees->count() > 3)
                                        <span class="ml-2 text-[10px] font-semibold text-slate-500">+{{ $task->assignees->count() - 3 }}</span>
                                    @endif
                                </div>
                                <div class="mt-1 truncate text-[10px] text-slate-400" title="{{ $task->assignees->pluck('name')->join(', ') }}">{{ $task->assignees->pluck('name')->join(', ') }}</div>
                                @if ($task->reviewer)
                                    <div class="mt-1 truncate text-[10px] font-semibold text-sky-600" title="Reviewer: {{ $task->reviewer->name }}">Reviewer: {{ $task->reviewer->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-xs font-semibold {{ $isOverdue ? 'text-rose-600' : 'text-ink' }}">{{ $task->due_at?->translatedFormat('d M Y') ?? 'Belum ditentukan' }}</div>
                                <div class="mt-1 text-[10px] {{ $isOverdue ? 'font-semibold text-rose-500' : 'text-slate-400' }}">{{ $task->due_at?->format('H:i') ?? 'Tanpa batas waktu' }}{{ $isOverdue ? ' · Terlambat' : '' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="badge {{ $priorityClass }}">{{ \App\Support\Crm::PRIORITIES[$task->priority] ?? $task->priority }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <form method="POST" action="{{ route('tasks.status', $task) }}" class="flex items-center gap-2" x-data="{ saving: false }">
                                    @csrf
                                    @method('PATCH')
                                    <select class="field !min-h-9 !py-1.5 !text-xs {{ $statusClass }}" name="status" aria-label="Status {{ $task->title }}" @change="saving = true; $el.form.requestSubmit()" :class="saving && 'opacity-60'">
                                        @foreach (\App\Support\Crm::TASK_STATUSES as $key => $label)
                                            <option value="{{ $key }}" @selected($task->status === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span x-show="saving" x-cloak class="whitespace-nowrap text-[10px] font-semibold text-brand-600">Menyimpan...</span>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="mx-auto grid size-11 place-items-center rounded-full bg-slate-100 text-slate-400">
                                    <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 3.5h10a1.5 1.5 0 0 1 1.5 1.5v11.5H3.5V5A1.5 1.5 0 0 1 5 3.5Z"/><path d="m7 10 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-slate-500">Tidak ada task yang sesuai.</p>
                                <p class="mt-1 text-xs text-slate-400">Coba ubah filter atau buat task baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-slate-100 md:hidden">
            @forelse ($tasks as $task)
                @php $isOverdue = $task->due_at?->isPast() && ! in_array($task->status, ['done', 'cancelled'], true); @endphp
                <article class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-ink">{{ $task->title }}</div>
                            <div class="mt-1 text-[10px] text-slate-400">{{ $task->customer?->company_name ?? 'Internal' }} · {{ $task->task_id }}</div>
                        </div>
                        <span class="badge {{ $task->priority === 'urgent' ? 'bg-rose-50 text-rose-600' : ($task->priority === 'high' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ \App\Support\Crm::PRIORITIES[$task->priority] ?? $task->priority }}</span>
                    </div>
                    @if ($task->description)
                        <p class="mt-3 line-clamp-2 text-xs leading-relaxed text-slate-500">{{ $task->description }}</p>
                    @endif
                    <div class="mt-3 flex items-center justify-between text-[11px]">
                        <span class="truncate text-slate-500">{{ $task->assignees->pluck('name')->join(', ') }}</span>
                        <span class="shrink-0 font-semibold {{ $isOverdue ? 'text-rose-600' : 'text-slate-500' }}">{{ $task->due_at?->translatedFormat('d M Y, H:i') ?? 'Belum ditentukan' }}</span>
                    </div>
                    @if ($task->reviewer)
                        <div class="mt-1 text-[10px] font-semibold text-sky-600">Reviewer: {{ $task->reviewer->name }}</div>
                    @endif
                    <form method="POST" action="{{ route('tasks.status', $task) }}" class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3" x-data="{ saving: false }">
                        @csrf
                        @method('PATCH')
                        <select class="field !py-2 text-xs" name="status" aria-label="Status {{ $task->title }}" @change="saving = true; $el.form.requestSubmit()" :class="saving && 'opacity-60'">
                            @foreach (\App\Support\Crm::TASK_STATUSES as $key => $label)
                                <option value="{{ $key }}" @selected($task->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span x-show="saving" x-cloak class="whitespace-nowrap text-[10px] font-semibold text-brand-600">Menyimpan...</span>
                    </form>
                </article>
            @empty
                <div class="p-12 text-center text-sm text-slate-400">Tidak ada task yang sesuai.</div>
            @endforelse
        </div>

        @if ($tasks->hasPages())
            <footer class="border-t border-slate-100 px-5 py-4">
                {{ $tasks->links() }}
            </footer>
        @endif
    </section>
@endsection
