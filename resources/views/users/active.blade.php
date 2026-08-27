@extends('layouts.app')
@section('title', 'Pengguna Aktif')
@section('eyebrow', 'Administrasi · Pemantauan')
@section('page_title', 'Pengguna Aktif')
@section('content')
<div x-data x-init="setTimeout(() => window.location.reload(), 30000)" class="space-y-5">
    <section class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-ink">Aktivitas pengguna saat ini</h2>
                <p class="mt-1 text-xs text-slate-400">Online diperbarui otomatis. Aktivitas yang ditampilkan hanya halaman CRM yang sedang dibuka.</p>
            </div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                <span class="size-2 rounded-full bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,.12)]"></span>
                {{ $users->filter(fn($user) => $user->presence?->last_seen_at?->gte(now()->subMinutes(2)))->count() }} online
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left">
                <thead class="table-head"><tr><th class="px-5 py-3">Pengguna</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Sedang melakukan</th><th class="px-5 py-3 text-right">Terakhir aktif</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $activeUser)
                        @php($online = $activeUser->presence?->last_seen_at?->gte(now()->subMinutes(2)))
                        <tr>
                            <td class="px-5 py-4"><div class="flex items-center gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-50 text-[11px] font-extrabold text-brand-600">{{ collect(explode(' ', $activeUser->name))->filter()->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('') }}</span><span><span class="block text-sm font-bold text-ink">{{ $activeUser->name }}</span><span class="block text-[11px] text-slate-400">{{ $activeUser->roleNames() ?: ucfirst(str_replace('_', ' ', $activeUser->authority_level)) }} · {{ $activeUser->employee_id }}</span></span></div></td>
                            <td class="px-4 py-4"><span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $online ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}"><span class="size-1.5 rounded-full {{ $online ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ $online ? 'Online' : 'Baru saja offline' }}</span></td>
                            <td class="px-4 py-4"><div class="text-sm font-semibold text-slate-700">{{ $activeUser->presence?->current_page ?: 'Menggunakan CRM' }}</div><div class="mt-1 max-w-md truncate text-[11px] text-slate-400">{{ $activeUser->presence?->current_path }}</div></td>
                            <td class="px-5 py-4 text-right"><div class="text-xs font-semibold text-slate-600">{{ $activeUser->presence?->last_seen_at?->diffForHumans() }}</div><div class="mt-1 text-[10px] text-slate-400">{{ $activeUser->presence?->last_seen_at?->format('d M Y, H:i:s') }}</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-14 text-center"><div class="text-sm font-bold text-slate-600">Belum ada pengguna aktif</div><div class="mt-1 text-xs text-slate-400">Data akan muncul saat pengguna membuka CRM.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
