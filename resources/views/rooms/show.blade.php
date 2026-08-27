@extends('layouts.app')
@section('title',$room->name)
@section('eyebrow','Customer room / '.$room->customer->company_name)
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3"><a href="{{ route('customers.show',$room->customer) }}" class="text-sm font-bold text-brand-600">← Kembali ke customer</a><div class="flex gap-2"><a href="{{ route('tasks.create',['room'=>$room,'customer'=>$room->customer_id]) }}" class="btn-secondary">＋ Task</a><a href="{{ route('activities.create',['customer'=>$room->customer_id]) }}" class="btn-primary">＋ Activity</a></div></div>
<div class="grid gap-6 xl:grid-cols-[1fr_340px]">
    <div class="space-y-6">
        <section class="card overflow-hidden" data-room-chat data-room-messages-url="{{ route('rooms.messages',$room) }}" data-current-user="{{ auth()->id() }}" data-last-message-id="{{ $room->comments->max('id') ?? 0 }}">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Diskusi room</h3><p class="mt-1 text-[10px] text-slate-400">Pesan baru muncul otomatis tanpa refresh.</p></div><span class="flex items-center gap-1.5 text-[10px] font-bold text-emerald-600"><span class="size-2 rounded-full bg-emerald-500"></span>Live</span></div>
            <div data-room-message-list class="scrollbar-thin max-h-[520px] min-h-52 overflow-y-auto divide-y divide-slate-100">
                @forelse($room->comments->sortBy('id') as $comment)
                    <article data-message-id="{{ $comment->id }}" class="flex gap-3 p-5 {{ $comment->user_id===auth()->id()?'bg-indigo-50/30':'' }}"><div class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-100 text-xs font-extrabold text-brand-700">{{ mb_substr($comment->user->name,0,1) }}</div><div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-3"><div class="text-sm font-bold text-ink">{{ $comment->user->name }}</div><time class="text-[10px] text-slate-400">{{ $comment->created_at->format('d M Y, H:i') }}</time></div><p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-600">{{ $comment->body }}</p></div></article>
                @empty
                    <div data-room-empty class="p-12 text-center text-sm text-slate-400">Belum ada diskusi. Mulai kirim update untuk anggota room.</div>
                @endforelse
            </div>
            <form data-room-message-form method="POST" action="{{ route('rooms.comments',$room) }}" class="border-t border-slate-100 bg-slate-50/70 p-4">@csrf<div class="flex items-end gap-3"><textarea class="field min-h-12 flex-1 resize-none bg-white" rows="2" name="body" placeholder="Tulis pesan untuk anggota room..." required></textarea><button class="btn-primary mb-0.5 shrink-0">Kirim</button></div><p data-room-send-status class="mt-2 hidden text-[10px] text-slate-400"></p></form>
        </section>

        <section class="card overflow-hidden"><div class="border-b border-slate-100 p-5"><h3 class="section-title">Room tasks</h3></div><div class="divide-y divide-slate-100">@forelse($room->tasks as $task)<div class="flex items-center gap-3 p-4"><span class="size-2 rounded-full {{ $task->status==='done'?'bg-emerald-500':'bg-amber-500' }}"></span><div class="min-w-0 flex-1"><div class="truncate text-sm font-bold text-ink">{{ $task->title }}</div><div class="text-[10px] text-slate-400">{{ $task->assignees->pluck('name')->join(', ') }}</div></div><span class="badge bg-slate-100 text-slate-600">{{ $task->status }}</span></div>@empty<div class="p-8 text-center text-sm text-slate-400">Belum ada task room.</div>@endforelse</div></section>
    </div>
    <aside class="space-y-6">
        <section class="card p-5"><h3 class="font-extrabold text-ink">Room access</h3><div class="mt-4 space-y-3">@foreach($room->members as $member)<div class="flex items-center gap-3"><div class="grid size-8 place-items-center rounded-lg bg-slate-100 text-[10px] font-bold">{{ mb_substr($member->user->name,0,1) }}</div><div class="min-w-0 flex-1"><div class="truncate text-xs font-bold">{{ $member->user->name }}</div><div class="text-[10px] capitalize text-slate-400">{{ $member->access_level }}</div></div></div>@endforeach</div></section>
        <section class="card p-5"><h3 class="font-extrabold text-ink">Invite backliner</h3><form method="POST" action="{{ route('rooms.invite',$room) }}" class="mt-4 space-y-3">@csrf<select class="field" name="user_id" required><option value="">Pilih user</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->user_type }}</option>@endforeach</select><select class="field" name="access_level">@foreach(['editor','contributor','commenter','viewer'] as $access)<option value="{{ $access }}">{{ ucfirst($access) }}</option>@endforeach</select><input type="datetime-local" class="field" name="expires_at"><button class="btn-primary w-full">Invite to room</button></form></section>
    </aside>
</div>
@endsection
