@extends('layouts.app')
@section('title','Audit Log')
@section('eyebrow','Administrasi / Riwayat perubahan')
@section('content')
@php
    $moduleLabels = [
        'customers' => 'Customer', 'leads' => 'Lead', 'opportunities' => 'Opportunity',
        'opportunity_items' => 'Produk opportunity', 'activities' => 'Aktivitas',
        'tasks' => 'Task', 'approvals' => 'Approval', 'pipelines' => 'Pipeline',
        'pipeline_stages' => 'Tahap pipeline', 'users' => 'Pengguna', 'roles' => 'Role',
        'areas' => 'Area', 'business_units' => 'Jenis customer',
        'approval_steps' => 'Tahap approval', 'activity_approval_details' => 'Detail approval aktivitas',
        'attachments' => 'Lampiran', 'comments' => 'Komentar', 'contacts' => 'Kontak',
        'departments' => 'Departemen', 'kpi_templates' => 'Template KPI', 'sales_kpi_targets' => 'Target KPI',
        'opportunity_stage_histories' => 'Riwayat tahap opportunity', 'permissions' => 'Hak akses',
        'products' => 'Produk', 'report_saved_filters' => 'Filter laporan', 'room_members' => 'Anggota ruang customer',
        'stage_rules' => 'Aturan tahap', 'system_settings' => 'Settings', 'teams' => 'Tim',
        'tenants' => 'Perusahaan', 'authentication' => 'Keamanan akun', 'kpi' => 'Laporan KPI', 'reports' => 'Laporan',
        'kpi_metrics' => 'KPI Metrics',
    ];
    $actionLabels = [
        'created' => 'Create', 'updated' => 'Update', 'deleted' => 'Delete',
        'relations_updated' => 'Relasi diperbarui', 'login' => 'Login', 'logout' => 'Logout',
        'login_failed' => 'Login gagal', 'password_changed' => 'Ganti password', 'exported' => 'Export',
        'setup_completed' => 'Setup diselesaikan',
    ];
@endphp

<div x-data="{
    selected: null,
    labels: {
        company_name: 'Nama perusahaan', brand_name: 'Nama brand', legal_name: 'Nama legal',
        customer_id: 'ID customer', lead_id: 'ID lead', owner_id: 'Sales penanggung jawab',
        manager_id: 'Manager', area_id: 'Area', business_unit_id: 'Jenis customer',
        city: 'Kota/Kabupaten', address: 'Alamat', phone: 'Nomor WhatsApp', email: 'Email', npwp: 'NPWP',
        status: 'Status', business_type: 'Jenis customer',
        credit_limit: 'Batas kredit', payment_term: 'Tempo pembayaran', estimated_monthly_purchase: 'Estimasi pembelian bulanan',
        title: 'Judul', description: 'Deskripsi', detail: 'Catatan', result: 'Hasil', next_action: 'Next action',
        follow_up_at: 'Jadwal follow-up', type: 'Jenis', priority: 'Prioritas', due_at: 'Batas waktu',
        pipeline_id: 'Pipeline', pipeline_stage_id: 'Tahap pipeline', probability: 'Probability', estimated_value: 'Nilai estimasi',
        product_name: 'Nama produk', quantity: 'Jumlah', unit: 'Satuan', customer_price: 'Harga customer', offered_price: 'Harga penawaran',
        lost_reason: 'Kategori alasan Lost', lost_reason_detail: 'Detail alasan Lost',
        roles: 'Role', departments: 'Departemen', permissions: 'Hak akses', denied_permissions: 'Hak akses ditolak',
        assigned_users: 'Pengguna yang ditugaskan', collaborators: 'Kolaborator', assignees: 'Pelaksana',
        name: 'Nama metrik', source: 'Sumber data', filters: 'Filter', unit: 'Satuan', threshold: 'Nilai minimum',
        is_active: 'Aktif', counts_in_achievement: 'Masuk pencapaian', sort_order: 'Urutan metrik', legacy_key: 'Kode metrik',
        sales_target: 'Target penjualan', noo_target: 'Target NOO', custom_noo_target: 'Target NOO Custom',
        large_account_target: 'Target Akun Besar', drink_volume_target: 'Target Drink', food_volume_target: 'Target Food',
        metric_targets: 'Target metrik', evaluation_notes: 'Catatan evaluasi', key: 'Kunci pengaturan', value: 'Nilai pengaturan'
    },
    ignored: ['id', 'created_at', 'updated_at', 'deleted_at'],
    fieldName(key) {
        return this.labels[key] || key.replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase())
    },
    valueText(value) {
        if (value === null || value === undefined || value === '') return 'Kosong'
        if (value === true || value === 1) return 'Ya'
        if (value === false || value === 0) return 'Tidak'
        if (typeof value === 'object') return JSON.stringify(value)
        return String(value).replaceAll('_', ' ')
    },
    changes() {
        if (!this.selected) return []
        const oldData = this.selected.old || {}
        const newData = this.selected.new || {}
        return [...new Set([...Object.keys(oldData), ...Object.keys(newData)])]
            .filter(key => !this.ignored.includes(key))
            .filter(key => JSON.stringify(oldData[key] ?? null) !== JSON.stringify(newData[key] ?? null))
            .map(key => ({ key, label: this.fieldName(key), old: this.valueText(oldData[key]), new: this.valueText(newData[key]) }))
    }
}" class="space-y-5">
    <div class="-mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Pantau perubahan penting di seluruh CRM dari satu tempat.</p>
        <div class="flex overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 px-4 py-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Hari ini</span>
                <span class="text-sm font-black text-ink">{{ number_format($todayCount) }} perubahan</span>
            </div>
            <div class="flex items-center gap-2 border-l border-slate-200 px-4 py-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pelaku aktif</span>
                <span class="text-sm font-black text-ink">{{ number_format($actorCount) }} orang</span>
            </div>
        </div>
    </div>

    <form method="GET" class="card p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_190px_160px_190px_150px_150px_auto]">
            <input class="field" name="search" value="{{ request('search') }}" placeholder="Cari modul, ID, atau pengguna...">
            <select class="field" name="module"><option value="">Semua modul</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ $moduleLabels[$module] ?? str($module)->headline() }}</option>@endforeach</select>
            <select class="field" name="action"><option value="">Semua tindakan</option>@foreach($actionLabels as $value => $label)<option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>@endforeach</select>
            <select class="field" name="user_id"><option value="">Semua pengguna</option>@foreach($users as $actor)<option value="{{ $actor->id }}" @selected((string) request('user_id') === (string) $actor->id)>{{ $actor->name }}</option>@endforeach</select>
            <input type="date" class="field" name="date_from" value="{{ request('date_from') }}" title="Dari tanggal">
            <input type="date" class="field" name="date_to" value="{{ request('date_to') }}" title="Sampai tanggal">
            <button class="btn-primary">Terapkan</button>
        </div>
        @if(request()->hasAny(['search','module','action','user_id','date_from','date_to']))<div class="mt-3"><a href="{{ route('audit.index') }}" class="text-xs font-semibold text-brand-600">Reset filter</a></div>@endif
    </form>

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="section-title">Riwayat perubahan</h2><p class="mt-1 text-xs text-slate-500">{{ number_format($logs->total()) }} catatan ditemukan</p></div><span class="text-xs text-slate-400">Klik detail untuk membandingkan data</span></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left">
                <thead class="table-head"><tr><th class="px-5 py-3">Waktu</th><th class="px-4 py-3">Pengguna</th><th class="px-4 py-3">Modul & data</th><th class="px-4 py-3">Tindakan</th><th class="px-4 py-3">Perubahan</th><th class="px-4 py-3">Alamat IP</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                    @php
                        $changedKeys = collect(array_keys($log->new_values ?? $log->old_values ?? []))->reject(fn($key) => in_array($key, ['created_at','updated_at'], true));
                        $payload = ['module' => $moduleLabels[$log->module] ?? str($log->module)->headline(), 'record' => $log->auditable_id, 'action' => $actionLabels[$log->action] ?? ucfirst($log->action), 'actor' => $log->user?->name ?? 'Sistem', 'time' => $log->created_at->format('d M Y, H:i:s'), 'ip' => $log->ip_address, 'reason' => $log->reason, 'old' => $log->old_values ?? [], 'new' => $log->new_values ?? []];
                        $encodedPayload = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    @endphp
                    <tr class="hover:bg-slate-50/70">
                        <td class="whitespace-nowrap px-5 py-4"><div class="text-xs font-bold text-ink">{{ $log->created_at->format('d M Y') }}</div><div class="mt-1 text-[11px] text-slate-400">{{ $log->created_at->format('H:i:s') }}</div></td>
                        <td class="px-4 py-4"><div class="text-xs font-bold text-ink">{{ $log->user?->name ?? 'Sistem' }}</div><div class="mt-1 text-[10px] text-slate-400">{{ $log->user?->employee_id ?? 'Proses otomatis' }}</div></td>
                        <td class="px-4 py-4"><div class="text-xs font-bold text-ink">{{ $moduleLabels[$log->module] ?? str($log->module)->headline() }}</div><div class="mt-1 text-[10px] text-slate-400">Record #{{ $log->auditable_id ?? '—' }}</div></td>
                        <td class="px-4 py-4"><span class="badge {{ $log->action === 'deleted' ? 'bg-rose-50 text-rose-600' : ($log->action === 'created' ? 'bg-emerald-50 text-emerald-600' : 'bg-sky-50 text-sky-600') }}">{{ $actionLabels[$log->action] ?? ucfirst($log->action) }}</span></td>
                        <td class="max-w-[260px] px-4 py-4 text-xs text-slate-500">{{ $changedKeys->isNotEmpty() ? $changedKeys->take(3)->map(fn($key) => str($key)->replace('_',' ')->title())->join(', ').($changedKeys->count() > 3 ? ' +'.($changedKeys->count()-3) : '') : 'Tidak ada rincian nilai' }}</td>
                        <td class="px-4 py-4 text-xs text-slate-500">{{ $log->ip_address ?: '—' }}</td>
                        <td class="px-5 py-4 text-right"><button type="button" class="btn-secondary h-9 px-4 text-xs" data-payload="{{ $encodedPayload }}" @click="selected = JSON.parse(new TextDecoder().decode(Uint8Array.from(atob($el.dataset.payload), character => character.charCodeAt(0))))">Detail</button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-16 text-center"><div class="text-sm font-bold text-slate-500">Belum ada catatan yang sesuai</div><p class="mt-1 text-xs text-slate-400">Ubah atau reset filter untuk melihat riwayat lainnya.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div>{{ $logs->links() }}</div>

    <div x-show="selected" x-cloak class="fixed inset-0 z-[90] grid place-items-center bg-slate-950/50 p-4 backdrop-blur-sm" @keydown.escape.window="selected = null" @click.self="selected = null">
        <div class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex items-start justify-between border-b border-slate-200 px-6 py-5"><div><h3 class="text-lg font-black text-ink">Detail perubahan</h3><p class="mt-1 text-xs text-slate-500" x-text="selected ? `${selected.module} #${selected.record} · ${selected.actor}` : ''"></p></div><button type="button" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500" @click="selected = null" aria-label="Tutup"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button></header>
            <div class="max-h-[70vh] overflow-y-auto p-6">
                <template x-if="selected"><div>
                    <div class="mb-5 grid gap-3 sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-4"><div class="text-[10px] font-bold uppercase text-slate-400">Tindakan</div><div class="mt-1 text-sm font-bold" x-text="selected.action"></div></div><div class="rounded-xl bg-slate-50 p-4"><div class="text-[10px] font-bold uppercase text-slate-400">Waktu</div><div class="mt-1 text-sm font-bold" x-text="selected.time"></div></div><div class="rounded-xl bg-slate-50 p-4"><div class="text-[10px] font-bold uppercase text-slate-400">Alamat IP</div><div class="mt-1 text-sm font-bold" x-text="selected.ip || '—'"></div></div></div>
                    <div x-show="selected.reason" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3"><div class="text-[10px] font-bold uppercase text-amber-600">Keterangan</div><div class="mt-1 text-sm text-amber-900" x-text="selected.reason"></div></div>
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-3">
                            <div>
                                <h4 class="text-sm font-black text-ink">Data yang berubah</h4>
                                <p class="mt-0.5 text-[11px] text-slate-500">Hanya menampilkan informasi yang nilainya berbeda.</p>
                            </div>
                            <span class="rounded-full bg-brand-50 px-3 py-1 text-[11px] font-bold text-brand-600" x-text="`${changes().length} perubahan`"></span>
                        </div>
                        <div class="divide-y divide-slate-100" x-show="changes().length">
                            <template x-for="change in changes()" :key="change.key">
                                <div class="grid gap-3 px-5 py-4 md:grid-cols-[190px_minmax(0,1fr)_28px_minmax(0,1fr)] md:items-center">
                                    <div class="text-xs font-bold text-slate-600" x-text="change.label"></div>
                                    <div class="min-w-0 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700 break-words" x-text="change.old"></div>
                                    <div class="hidden text-center text-slate-300 md:block">→</div>
                                    <div class="min-w-0 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 break-words" x-text="change.new"></div>
                                </div>
                            </template>
                        </div>
                        <div x-show="!changes().length" class="px-5 py-10 text-center">
                            <div class="text-sm font-bold text-slate-500">Tidak ada perubahan nilai yang perlu ditampilkan</div>
                            <p class="mt-1 text-xs text-slate-400">Catatan ini mungkin hanya memperbarui waktu sistem.</p>
                        </div>
                    </div>
                </div></template>
            </div>
        </div>
    </div>
</div>
@endsection
