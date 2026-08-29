<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Unified CRM</title>
    <script>
        (() => {
            const saved = localStorage.getItem('crm-theme');
            const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-notification-poll-url="{{ route('notifications.poll') }}" data-notification-user="{{ auth()->id() }}" data-presence-heartbeat-url="{{ route('presence.heartbeat') }}">
@php
    $user = auth()->user();
    $canMonitorPresence = $user->isMasterAdmin() || in_array($user->authority_level, ['manager', 'supervisor'], true);
    $groups = [
        'Utama' => [
            ['dashboard', 'Ringkasan', 'dashboard.view', 'home'],
            ['tasks.index', 'Task', 'tasks.view', 'check'],
            ['notifications.index', 'Notifikasi', null, 'bell'],
            ['approvals.index', 'Approval', 'approvals.view', 'approval'],
        ],
        'CRM & Penjualan' => [
            [$user->canAccess('customers.view') ? 'customers.index' : 'leads.index', 'Customer & Lead', $user->canAccess('customers.view') ? 'customers.view' : 'leads.view', 'customer'],
            ['opportunities.index', 'Opportunity', 'opportunities.view', 'opportunity'],
            ['activities.index', 'Aktivitas', 'activities.view', 'activity'],
        ],
        'Laporan' => [
            ['kpi.index', 'KPI', 'kpi.view', 'kpi'],
            ['reports.index', 'Laporan Leads', 'reports.view', 'report'],
        ],
        'Administrasi' => [
            ['users.index', 'Pengguna', 'admin.manage', 'users'],
            ['areas.index', 'Area & Cabang', 'admin.manage', 'area'],
            ['roles.index', 'Role & Hak Akses', 'admin.manage', 'shield'],
            ['settings.customer-types.index', 'Settings', 'admin.manage', 'settings'],
            ['audit.index', 'Audit Log', 'admin.manage', 'audit'],
        ],
    ];
    $icons = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5M9 21v-7h6v7"/>',
        'check' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'approval' => '<path d="M9 12l2 2 4-4"/><path d="M12 22C7 20 4 17 4 11V5l8-3 8 3v6c0 6-3 9-8 11Z"/>',
        'lead' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2M18 3l3 3m0-3-3 3"/>',
        'customer' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'opportunity' => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'kanban' => '<rect x="3" y="3" width="7" height="18" rx="2"/><rect x="14" y="3" width="7" height="11" rx="2"/>',
        'activity' => '<path d="M3 12h4l3-9 4 18 3-9h4"/>',
        'report' => '<path d="M3 3v18h18"/><path d="m7 16 4-5 4 3 5-7"/>',
        'kpi' => '<path d="M4 19V9m6 10V5m6 14v-7m4 7H2"/><path d="m4 6 6-3 6 5 4-3"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6m3-3h-6"/>',
        'area' => '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'shield' => '<path d="M12 22C7 20 4 17 4 11V5l8-3 8 3v6c0 6-3 9-8 11Z"/><path d="m9 12 2 2 4-4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'audit' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
    ];
    $quickItems = collect([
        ['leads.create','Lead baru','Calon customer baru','leads.view'],
        ['opportunities.create','Opportunity baru','Buat peluang penjualan','opportunities.view'],
        ['activities.create','Catat aktivitas','Meeting, telepon, kunjungan','activities.view'],
        ['tasks.create','Buat task','Tetapkan task dan batas waktu','tasks.view'],
    ])->filter(fn ($item) => $user->canAccess($item[3]));
    $mobileItems = collect([
        ['dashboard','Ringkasan','home','dashboard.view'],
        ['tasks.index','Task','check','tasks.view'],
        [$user->canAccess('customers.view') ? 'customers.index' : 'leads.index','Customer & Lead','customer',$user->canAccess('customers.view') ? 'customers.view' : 'leads.view'],
        ['opportunities.index','Opportunity','opportunity','opportunities.view'],
    ])->filter(fn ($item) => $user->canAccess($item[3]));
    $isNavActive = function (string $route): bool {
        return match ($route) {
            'opportunities.index' => request()->routeIs('opportunities.index', 'opportunities.create', 'opportunities.show', 'opportunities.kanban', 'pipelines.*'),
            'users.index' => request()->routeIs('users.index', 'users.create', 'users.edit'),
            'users.active' => request()->routeIs('users.active'),
            default => request()->routeIs(str_replace('.index', '.*', $route)) || request()->routeIs($route),
        };
    };
@endphp
<div x-data="{
    sidebar: false,
    quick: false,
    profile: false,
    notificationsOpen: false,
    presenceOpen: false,
    presenceLoading: false,
    presenceUsers: [],
    settingsOpen: @js(request()->routeIs('settings.customer-types.*', 'settings.activity-evidence.*', 'settings.validation.*', 'pipelines.*')),
    async loadPresence() {
        this.presenceOpen = !this.presenceOpen;
        if (!this.presenceOpen) return;
        this.notificationsOpen = false;
        this.presenceLoading = true;
        try {
            const response = await fetch('{{ $canMonitorPresence ? route('users.active.data') : '' }}', { headers: { Accept: 'application/json' } });
            const result = await response.json();
            this.presenceUsers = result.users || [];
        } finally {
            this.presenceLoading = false;
        }
    },
    theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
    toggleTheme() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.classList.toggle('dark', this.theme === 'dark');
        localStorage.setItem('crm-theme', this.theme);
    }
}" class="min-h-screen">
    <div x-show="sidebar" x-cloak @click="sidebar=false" class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm lg:hidden"></div>

    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'" class="group/sidebar fixed inset-y-0 left-0 z-50 flex w-[248px] flex-col overflow-visible border-r border-slate-200 bg-white transition-[width,transform,box-shadow] duration-200 ease-out lg:w-[72px] lg:translate-x-0 lg:hover:w-[248px] lg:hover:shadow-2xl lg:hover:shadow-slate-950/10">
        <div class="flex h-[72px] items-center gap-3 overflow-hidden border-b border-slate-100 px-5 lg:justify-center lg:px-3 lg:group-hover/sidebar:justify-start lg:group-hover/sidebar:px-5">
            <div class="grid size-9 place-items-center rounded-xl bg-brand-600 text-xs font-black tracking-tight text-white shadow-sm">UC</div>
            <div class="min-w-0 flex-1 transition-all duration-200 lg:max-w-0 lg:overflow-hidden lg:whitespace-nowrap lg:opacity-0 lg:group-hover/sidebar:max-w-40 lg:group-hover/sidebar:opacity-100"><div class="truncate text-sm font-extrabold tracking-tight text-ink">Unified CRM</div><div class="whitespace-nowrap text-[9px] font-bold uppercase tracking-[.18em] text-slate-400">Sales workspace</div></div>
            <button @click="sidebar=false" class="icon-btn lg:hidden" aria-label="Tutup menu">×</button>
        </div>

        <nav class="sidebar-scroll flex-1 overflow-x-hidden overflow-y-auto px-3 py-4">
            @foreach($groups as $group => $items)
                @php($visibleItems = collect($items)->filter(fn($item) => ! $item[2] || $user->canAccess($item[2])))
                @continue($visibleItems->isEmpty())
                <div class="mb-5">
                    <div class="mb-1 overflow-hidden whitespace-nowrap px-3 text-[9px] font-extrabold uppercase tracking-[.16em] text-slate-400 transition-opacity duration-200 lg:opacity-0 lg:group-hover/sidebar:opacity-100">{{ $group }}</div>
                    <div class="space-y-0.5">
                        @foreach($visibleItems as [$route, $label, $permission, $icon])
                            @php($active = $isNavActive($route))
                            @if($route === 'settings.customer-types.index')
                            @php($active = request()->routeIs('settings.customer-types.*', 'settings.activity-evidence.*', 'settings.validation.*', 'pipelines.*'))
                            <button type="button" data-settings-nav title="{{ $label }}" @click="settingsOpen = !settingsOpen" :aria-expanded="settingsOpen" class="nav-item relative w-full {{ $active ? 'nav-item-active' : '' }} lg:justify-center lg:gap-0 lg:px-0 lg:group-hover/sidebar:justify-start lg:group-hover/sidebar:gap-3 lg:group-hover/sidebar:px-3">
                                <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$icon] !!}</svg>
                                <span class="min-w-0 overflow-hidden whitespace-nowrap text-left opacity-100 transition-all duration-200 lg:max-w-0 lg:flex-none lg:opacity-0 lg:group-hover/sidebar:max-w-40 lg:group-hover/sidebar:flex-1 lg:group-hover/sidebar:opacity-100">{{ $label }}</span>
                                <svg class="size-3.5 shrink-0 text-slate-400 transition-all duration-200 lg:w-0 lg:opacity-0 lg:group-hover/sidebar:w-3.5 lg:group-hover/sidebar:opacity-100" :class="settingsOpen && 'rotate-90'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                            </button>
                            <div data-settings-submenu x-show="settingsOpen" x-cloak x-transition.opacity.duration.150ms class="ml-7 mt-1 max-w-[190px] space-y-0.5 overflow-hidden border-l border-slate-200 pl-2 lg:invisible lg:hidden lg:group-hover/sidebar:visible lg:group-hover/sidebar:block">
                                <a href="{{ route('pipelines.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[11px] font-bold transition hover:bg-brand-50 hover:text-brand-700 {{ request()->routeIs('pipelines.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500' }}">
                                    <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="5" height="16" rx="1"/><rect x="10" y="4" width="5" height="10" rx="1"/><rect x="17" y="4" width="4" height="7" rx="1"/></svg>
                                    <span>Pipeline</span>
                                </a>
                                <a href="{{ route('settings.customer-types.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[11px] font-bold transition hover:bg-brand-50 hover:text-brand-700 {{ request()->routeIs('settings.customer-types.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500' }}">
                                    <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h10"/><circle cx="18" cy="17" r="2"/></svg>
                                    <span>Jenis Customer</span>
                                </a>
                                <a href="{{ route('settings.activity-evidence.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[11px] font-bold transition hover:bg-brand-50 hover:text-brand-700 {{ request()->routeIs('settings.activity-evidence.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500' }}">
                                    <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4z"/><path d="m8 14 2-2 2 2 3-4 3 4"/><circle cx="9" cy="9" r="1"/></svg>
                                    <span>Kebijakan Bukti</span>
                                </a>
                                <a href="{{ route('settings.validation.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[11px] font-bold transition hover:bg-brand-50 hover:text-brand-700 {{ request()->routeIs('settings.validation.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-500' }}">
                                    <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12h14M12 5v14"/><circle cx="12" cy="12" r="9"/></svg>
                                    <span>Validasi Data</span>
                                </a>
                            </div>
                            @continue
                            @endif
                            <a href="{{ route($route) }}" title="{{ $label }}" class="nav-item relative {{ $active ? 'nav-item-active' : '' }} lg:justify-center lg:gap-0 lg:px-0 lg:group-hover/sidebar:justify-start lg:group-hover/sidebar:gap-3 lg:group-hover/sidebar:px-3">
                                <svg class="size-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$icon] !!}</svg>
                                <span class="min-w-0 overflow-hidden whitespace-nowrap opacity-100 transition-all duration-200 lg:max-w-0 lg:flex-none lg:opacity-0 lg:group-hover/sidebar:max-w-40 lg:group-hover/sidebar:flex-1 lg:group-hover/sidebar:opacity-100">{{ $label }}</span>
                                @if($route === 'notifications.index')<span data-notification-count class="{{ $unread ? 'grid' : 'hidden' }} min-w-5 place-items-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[9px] font-bold text-white lg:absolute lg:right-0 lg:top-0 lg:min-w-4 lg:px-1 lg:group-hover/sidebar:static lg:group-hover/sidebar:min-w-5 lg:group-hover/sidebar:px-1.5">{{ $unread }}</span>@endif
                                @if($route === 'approvals.index')
                                    @if($approvalWaiting)<span class="grid min-w-5 place-items-center rounded-full bg-amber-500 px-1.5 py-0.5 text-[9px] font-bold text-white lg:absolute lg:right-0 lg:top-0 lg:min-w-4 lg:px-1 lg:group-hover/sidebar:static lg:group-hover/sidebar:min-w-5 lg:group-hover/sidebar:px-1.5">{{ $approvalWaiting }}</span>@endif
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-slate-100 p-3">
            <button @click="profile=!profile" title="{{ $user->name }}" class="flex w-full items-center gap-3 rounded-xl p-2 text-left transition hover:bg-slate-50 lg:justify-center lg:gap-0 lg:group-hover/sidebar:justify-start lg:group-hover/sidebar:gap-3">
                <div class="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 text-xs font-extrabold text-white">{{ mb_substr($user->name, 0, 1) }}</div>
                <div class="min-w-0 flex-1 transition-all duration-200 lg:max-w-0 lg:overflow-hidden lg:whitespace-nowrap lg:opacity-0 lg:group-hover/sidebar:max-w-40 lg:group-hover/sidebar:opacity-100"><div class="truncate text-xs font-bold text-ink">{{ $user->name }}</div><div class="truncate text-[10px] capitalize text-slate-400">{{ str_replace('_', ' ', $user->authority_level) }}</div></div>
                <svg class="size-4 shrink-0 text-slate-400 transition-all duration-200 lg:w-0 lg:overflow-hidden lg:opacity-0 lg:group-hover/sidebar:w-4 lg:group-hover/sidebar:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
            </button>
            <form x-show="profile" x-cloak method="POST" action="{{ route('logout') }}" class="mt-1 lg:hidden lg:group-hover/sidebar:block">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-rose-600 hover:bg-rose-50">Keluar dari aplikasi</button></form>
        </div>
    </aside>

    <main class="min-w-0 pb-20 lg:ml-[72px] lg:pb-0">
        <header class="sticky top-0 z-[100] flex h-[72px] items-center gap-3 border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl md:px-6">
            <button @click="sidebar=true" class="icon-btn lg:hidden" aria-label="Buka menu"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            @if($user->canAccess('opportunities.view'))
            <form action="{{ route('opportunities.index') }}" class="relative hidden w-full max-w-md sm:block">
                <svg class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input name="search" class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-xs transition placeholder:text-slate-400 focus:border-brand-400 focus:bg-white focus:ring-4 focus:ring-brand-500/10" placeholder="Cari opportunity atau customer...">
            </form>
            @endif
            <div class="ml-auto flex items-center gap-2">
                <button type="button" class="icon-btn relative" @click="toggleTheme()" :aria-label="theme === 'dark' ? 'Gunakan mode terang' : 'Gunakan mode gelap'" :title="theme === 'dark' ? 'Mode terang' : 'Mode gelap'">
                    <svg x-show="theme === 'light'" width="18" height="18" class="block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 3V1M12 23v-2M3 12H1M23 12h-2M4.22 4.22 2.8 2.8M21.2 21.2l-1.42-1.42M19.78 4.22 21.2 2.8M2.8 21.2l1.42-1.42"/><circle cx="12" cy="12" r="4"/></svg>
                    <svg x-show="theme === 'dark'" x-cloak width="17" height="17" class="block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/></svg>
                </button>
                @if($canMonitorPresence)
                <div class="relative" @click.outside="presenceOpen=false">
                    <button type="button" class="icon-btn relative" @click="loadPresence()" :aria-expanded="presenceOpen" aria-label="Pengguna aktif" title="Pengguna aktif">
                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                        <span class="absolute bottom-2 right-2 size-2 rounded-full border-2 border-white bg-emerald-500"></span>
                    </button>
                    <div x-show="presenceOpen" x-cloak x-transition.origin.top.right class="absolute right-0 top-12 z-[110] w-[min(390px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/15">
                        <header class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <div><div class="text-sm font-extrabold text-ink">Pengguna aktif</div><div class="mt-0.5 text-[10px] text-slate-400">Aktivitas pengguna di dalam CRM</div></div>
                            <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-600" x-text="`${presenceUsers.filter(user => user.online).length} online`"></span>
                        </header>
                        <div class="max-h-[420px] divide-y divide-slate-100 overflow-y-auto">
                            <div x-show="presenceLoading" class="px-6 py-10 text-center text-xs text-slate-400">Memuat pengguna...</div>
                            <template x-for="activeUser in presenceUsers" :key="activeUser.id">
                                <div class="flex gap-3 px-4 py-3">
                                    <span class="relative grid size-9 shrink-0 place-items-center rounded-xl bg-brand-50 text-[10px] font-extrabold text-brand-600"><span x-text="activeUser.initials"></span><span class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full border-2 border-white" :class="activeUser.online ? 'bg-emerald-500' : 'bg-slate-300'"></span></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-xs font-extrabold text-ink" x-text="activeUser.name"></span><span class="mt-0.5 block truncate text-[10px] text-slate-400" x-text="activeUser.role"></span><span class="mt-1.5 block truncate text-[11px] font-semibold text-slate-600" x-text="activeUser.page"></span></span>
                                    <span class="shrink-0 pt-0.5 text-[9px] font-semibold text-slate-400" x-text="activeUser.online ? 'Online' : activeUser.last_seen"></span>
                                </div>
                            </template>
                            <div x-show="!presenceLoading && presenceUsers.length === 0" class="px-6 py-10 text-center"><div class="text-sm font-bold text-slate-600">Belum ada pengguna aktif</div><p class="mt-1 text-[11px] text-slate-400">Data muncul saat pengguna membuka CRM.</p></div>
                        </div>
                    </div>
                </div>
                @endif
                <div class="relative" @click.outside="notificationsOpen=false">
                    <button type="button" class="icon-btn relative" @click="notificationsOpen=!notificationsOpen" :aria-expanded="notificationsOpen" aria-label="Notifikasi">
                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                        <span data-notification-dot class="absolute right-2 top-2 size-1.5 rounded-full bg-rose-500 ring-2 ring-white {{ $unread ? '' : 'hidden' }}"></span>
                    </button>
                    <div x-show="notificationsOpen" x-cloak x-transition.origin.top.right class="absolute right-0 top-12 z-[110] w-[min(390px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/15">
                        <header class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <div>
                                <div class="text-sm font-extrabold text-ink">Notifikasi</div>
                                <div class="mt-0.5 text-[10px] text-slate-400">Update terbaru untuk akun Anda</div>
                            </div>
                            <span data-header-notification-count class="{{ $unread ? '' : 'hidden' }} rounded-full bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-600">{{ $unread }} baru</span>
                        </header>
                        <div data-header-notification-list class="max-h-[420px] divide-y divide-slate-100 overflow-y-auto">
                            @foreach($headerNotifications as $notification)
                                @continue(!is_array($notification) || !isset($notification['id']))
                                <form method="POST" action="{{ route('notifications.read', $notification['id']) }}">
                                    @csrf
                                    <button class="flex w-full gap-3 px-4 py-3 text-left transition hover:bg-slate-50">
                                        <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $notification['is_read'] ? 'bg-slate-200' : 'bg-brand-500 ring-4 ring-brand-50' }}"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-xs font-extrabold text-ink">{{ $notification['title'] }}</span>
                                            <span class="mt-1 line-clamp-2 block text-[11px] leading-relaxed text-slate-500">{{ $notification['message'] }}</span>
                                            <span class="mt-1.5 block text-[9px] font-semibold text-slate-400">{{ $notification['created_ago'] }}</span>
                                        </span>
                                    </button>
                                </form>
                            @endforeach
                            @foreach($headerFollowUps as $followUp)
                                @continue(!is_array($followUp) || !isset($followUp['id']))
                                <a href="{{ route('activities.follow-up', $followUp['id']) }}" class="flex w-full gap-3 px-4 py-3 text-left transition hover:bg-slate-50">
                                    <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $followUp['is_overdue'] ? 'bg-rose-500 ring-4 ring-rose-50' : 'bg-amber-400 ring-4 ring-amber-50' }}"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-xs font-extrabold text-ink">{{ $followUp['is_overdue'] ? 'Follow-up terlambat' : 'Follow-up segera' }}</span>
                                        <span class="mt-1 line-clamp-2 block text-[11px] leading-relaxed text-slate-500">{{ $followUp['summary'] }} · {{ $followUp['customer_name'] }}</span>
                                        <span class="mt-1.5 block text-[9px] font-semibold {{ $followUp['is_overdue'] ? 'text-rose-500' : 'text-amber-600' }}">{{ $followUp['due_ago'] }}</span>
                                    </span>
                                </a>
                            @endforeach
                            @if(empty($headerNotifications) && empty($headerFollowUps))
                                <div data-header-notification-empty class="px-6 py-10 text-center"><div class="text-sm font-bold text-slate-600">Belum ada notifikasi</div><p class="mt-1 text-[11px] text-slate-400">Update terbaru akan tampil di sini.</p></div>
                            @endif
                        </div>
                        <a href="{{ route('notifications.index') }}" class="block border-t border-slate-100 bg-slate-50 px-4 py-3 text-center text-xs font-bold text-brand-600 hover:bg-brand-50">Lihat semua notifikasi</a>
                    </div>
                </div>
                @if($quickItems->isNotEmpty())<button @click="quick=!quick" class="btn-primary"><span class="text-lg leading-none">+</span><span class="hidden sm:inline">Buat baru</span></button>@endif
            </div>
        </header>

        <div class="px-4 py-5 md:px-6 md:py-6">
            <div class="mb-6 flex min-w-0 items-end justify-between gap-4">
                <div class="min-w-0"><div class="truncate text-[10px] font-extrabold uppercase tracking-[.14em] text-brand-600">@yield('eyebrow', 'Workspace')</div><h1 class="mt-1 truncate text-[22px] font-extrabold tracking-tight text-ink">@yield('title', 'Dashboard')</h1></div>
                @yield('page-actions')
            </div>
            @if(session('success'))
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 7000)"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-3"
                    x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-3"
                    role="status"
                    aria-live="polite"
                    class="fixed inset-x-4 top-20 z-[200] sm:left-auto sm:right-5 sm:w-[360px]"
                >
                    <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-white p-4 shadow-2xl shadow-slate-900/15">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-extrabold text-slate-900">Berhasil</div>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-600">{{ session('success') }}</p>
                        </div>
                        <button type="button" @click="show = false" class="grid size-7 shrink-0 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup notifikasi">
                            <svg class="size-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3 3 13"/></svg>
                        </button>
                    </div>
                </div>
            @endif
            @if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><div class="mb-1 font-bold">Ada data yang perlu diperbaiki:</div><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </main>

    @if($quickItems->isNotEmpty())
    <div x-show="quick" x-cloak @click.outside="quick=false" class="fixed right-4 top-[66px] z-50 w-72 rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl shadow-slate-900/10">
        <div class="px-3 pb-2 pt-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Buat data baru</div>
        @foreach($quickItems as [$route,$label,$hint,$permission])
            <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-brand-50"><span class="grid size-8 place-items-center rounded-lg bg-brand-100 text-lg font-medium text-brand-700">+</span><span><span class="block text-xs font-bold text-ink">{{ $label }}</span><span class="block text-[10px] text-slate-400">{{ $hint }}</span></span></a>
        @endforeach
    </div>
    @endif

    @if($mobileItems->isNotEmpty())
    <nav class="fixed inset-x-0 bottom-0 z-40 grid border-t border-slate-200 bg-white/95 px-2 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden" style="grid-template-columns: repeat({{ $mobileItems->count() }}, minmax(0, 1fr))">
        @foreach($mobileItems as [$route,$label,$icon,$permission])
            <a href="{{ route($route) }}" class="flex flex-col items-center gap-1 py-2 text-[9px] font-bold {{ $isNavActive($route) ? 'text-brand-600' : 'text-slate-400' }}"><svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$icon] !!}</svg>{{ $label }}</a>
        @endforeach
    </nav>
    @endif
</div>
</body>
</html>
