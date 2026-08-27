@php
    $uninvitedUsers = $activityDiscussionUsers->reject(fn ($user) =>
        (int) $user->id === (int) $activity->user_id ||
        in_array((int) $user->id, array_map('intval', $activity->participants ?? []), true)
    );
@endphp
<section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3">
        <div><h4 class="text-[12px] font-extrabold text-ink">Diskusi aktivitas</h4><p class="mt-0.5 text-[10px] text-slate-400">Libatkan rekan dan diskusikan aktivitas ini tanpa berpindah halaman.</p></div>
        <div class="flex items-center gap-3">
            <div class="flex -space-x-1.5">
                <span class="grid size-7 place-items-center rounded-full border-2 border-white bg-brand-100 text-[8px] font-black text-brand-700" title="{{ $activity->user->name }}">{{ mb_substr($activity->user->name, 0, 1) }}</span>
                @foreach(collect($activity->participants ?? [])->take(4) as $participantId)
                    @if($participantUsers->get($participantId))<span class="grid size-7 place-items-center rounded-full border-2 border-white bg-sky-100 text-[8px] font-black text-sky-700" title="{{ $participantUsers->get($participantId)->name }}">{{ mb_substr($participantUsers->get($participantId)->name, 0, 1) }}</span>@endif
                @endforeach
            </div>
            <button type="button" class="btn-secondary inline-flex h-8 items-center justify-center gap-1.5 px-3 text-[10px]" @click="inviteOpen=true"><svg class="block size-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 3v10M3 8h10"/></svg><span>Tambah orang</span></button>
        </div>
    </header>
    <div data-room-chat data-room-messages-url="{{ route('activities.comments', $activity) }}" data-current-user="{{ auth()->id() }}" data-last-message-id="{{ $activity->comments->max('id') ?? 0 }}">
        <div data-room-message-list class="max-h-72 divide-y divide-slate-100 overflow-y-auto">
            @forelse($activity->comments as $comment)
                <article data-message-id="{{ $comment->id }}" class="flex gap-3 p-4 {{ $comment->user_id === auth()->id() ? 'bg-indigo-50/30' : '' }}"><div class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-100 text-xs font-extrabold text-brand-700">{{ mb_substr($comment->user->name,0,1) }}</div><div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-3"><div class="text-sm font-bold text-ink">{{ $comment->user->name }}</div><time class="text-[10px] text-slate-400">{{ $comment->created_at->format('d M Y, H:i') }}</time></div><p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-slate-600">{{ $comment->body }}</p></div></article>
            @empty
                <div data-room-empty class="p-8 text-center text-xs text-slate-400">Belum ada komentar. Mulai diskusi untuk aktivitas ini.</div>
            @endforelse
        </div>
        <form data-room-message-form method="POST" action="{{ route('activities.comments.store',$activity) }}" class="border-t border-slate-100 bg-slate-50/70 p-4">@csrf<div class="flex flex-col gap-2 sm:flex-row sm:items-end"><textarea class="field min-h-12 flex-1 resize-none bg-white" rows="2" name="body" placeholder="Tulis komentar..." required></textarea><button class="btn-primary shrink-0">Kirim komentar</button></div><p data-room-send-status class="mt-2 hidden text-[10px] text-slate-400"></p></form>
    </div>
</section>

<template x-teleport="body">
    <div x-show="inviteOpen" x-cloak x-transition.opacity @keydown.escape.window="inviteOpen=false" @click.self="inviteOpen=false" class="fixed inset-0 z-[130] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-[2px]">
        <form x-show="inviteOpen" x-transition x-data="{selected:[],search:''}" method="POST" action="{{ route('activities.participants.store',$activity) }}" class="flex h-[520px] max-h-[78vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">@csrf
            <header class="flex items-start justify-between border-b border-slate-100 px-5 py-4"><div><h5 class="text-sm font-extrabold text-ink">Tambah orang ke diskusi</h5><p class="mt-1 text-[11px] text-slate-500">Pilih rekan yang perlu melihat aktivitas dan ikut berdiskusi.</p></div><button type="button" class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 p-0 text-slate-500" @click="inviteOpen=false" aria-label="Tutup"><svg class="block size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button></header>
            <div class="border-b border-slate-100 p-4"><input x-model="search" class="field" placeholder="Cari nama atau ID akun..."></div>
            <div class="min-h-0 flex-1 overflow-y-auto p-3">@forelse($uninvitedUsers as $discussionUser)<label x-show="@js(mb_strtolower($discussionUser->name.' '.($discussionUser->employee_id ?? ''))).includes(search.trim().toLowerCase())" class="mb-1 flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-slate-50 has-[:checked]:bg-brand-50"><input x-model="selected" type="checkbox" name="participant_ids[]" value="{{ $discussionUser->id }}" class="size-4 rounded border-slate-300 text-brand-600"><span class="grid size-9 place-items-center rounded-full bg-sky-100 text-[10px] font-black text-sky-700">{{ mb_substr($discussionUser->name,0,1) }}</span><span><b class="block text-[13px] text-slate-800">{{ $discussionUser->name }}</b><small class="text-slate-400">{{ $discussionUser->employee_id ?: 'Akun CRM' }}</small></span></label>@empty<div class="p-10 text-center text-sm text-slate-500">Semua orang sudah dilibatkan.</div>@endforelse</div>
            <footer class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-5 py-3.5"><span class="text-[11px] text-slate-500"><b x-text="selected.length">0</b> orang dipilih</span><div class="flex gap-2"><button type="button" class="btn-secondary" @click="inviteOpen=false">Batal</button><button class="btn-primary disabled:opacity-50" :disabled="selected.length===0">Tambahkan</button></div></footer>
        </form>
    </div>
</template>
