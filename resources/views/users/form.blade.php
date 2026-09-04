@extends('layouts.app')

@section('title', $user->exists ? 'Edit User' : 'User Baru')
@section('eyebrow', 'Admin / Users')

@section('content')
@php
    $initialRole = (string) collect(old('role_ids', $user->exists ? $user->roles->pluck('id')->all() : []))->first();
    $initialUserType = old('user_type', $user->user_type ?: 'frontliner');
    $initialManager = (string) old('manager_id', $user->manager_id);
    $roleSlugs = $roles->mapWithKeys(fn($role) => [(string) $role->id => $role->slug]);
    $roleTypes = $roles->mapWithKeys(fn($role) => [(string) $role->id => in_array($role->slug, ['sales', 'telesales', 'csa', 'sales_supervisor', 'sales_manager'], true) ? 'frontliner' : (in_array($role->slug, ['master_admin', 'finance', 'purchasing', 'warehouse'], true) ? 'backliner' : null)]);
    $roleApproverDefaults = $roles->mapWithKeys(fn($role) => [(string) $role->id => in_array($role->slug, ['master_admin', 'sales_manager', 'sales_supervisor', 'csa'], true)]);
    $initialApprover = (string) old('is_approver', $user->exists ? (int) $user->is_approver : 0);
    $initialTenantIds = collect(old('tenant_ids', $selectedTenantIds))->map(fn ($id) => (int) $id)->all();
@endphp

<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" x-data="{ roleId: @js($initialRole), userType: @js($initialUserType), coordinatorId: @js($initialManager), isApprover: @js($initialApprover), roleSlugs: @js($roleSlugs), roleTypes: @js($roleTypes), roleApproverDefaults: @js($roleApproverDefaults), managerRequired() { return ['sales', 'telesales', 'csa', 'sales_supervisor'].includes(this.roleSlugs[this.roleId]); }, managerLabel() { return ['sales', 'telesales'].includes(this.roleSlugs[this.roleId]) ? 'CSA' : (['csa', 'sales_supervisor'].includes(this.roleSlugs[this.roleId]) ? 'Sales Manager' : 'Penanggung jawab'); }, managerHint() { return ['sales', 'telesales'].includes(this.roleSlugs[this.roleId]) ? 'Pilih CSA yang menangani Sales atau Telesales ini.' : (['csa', 'sales_supervisor'].includes(this.roleSlugs[this.roleId]) ? 'Pilih Sales Manager yang menangani pengguna ini.' : 'Pilih penanggung jawab pengguna bila diperlukan.'); }, syncRole() { if (this.roleTypes[this.roleId]) this.userType = this.roleTypes[this.roleId]; this.isApprover = this.roleApproverDefaults[this.roleId] ? '1' : '0'; if (['sales_manager', 'master_admin'].includes(this.roleSlugs[this.roleId])) this.coordinatorId = '' } }">
    @csrf
    @if($user->exists) @method('PUT') @endif

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4"><div class="text-xs font-extrabold text-rose-700">Akun belum dapat disimpan</div><ul class="mt-2 list-disc space-y-1 pl-5 text-[11px] text-rose-600">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="grid items-stretch gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <section class="card p-6">
            <div>
                <h3 class="section-title">Informasi akun</h3>
                <p class="mt-1 text-xs text-slate-400">Lengkapi identitas dan informasi login pengguna.</p>
            </div>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div><label class="label">Nama *</label><input class="field" name="name" value="{{ old('name', $user->name) }}" placeholder="Masukkan nama lengkap" autocomplete="name" required></div>
                <div><label class="label">Email *</label><input type="email" class="field" name="email" value="{{ old('email', $user->email) }}" placeholder="Masukkan alamat email" autocomplete="email" required></div>
                <div><label class="label">Phone</label><input class="field" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Masukkan nomor telepon" autocomplete="tel"></div>
                <div><label class="label">Role *</label><select class="field" name="role_ids[]" x-model="roleId" @change="syncRole()" required><option value="">Pilih role</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></div>
                <div><label class="label">Password {{ $user->exists ? '(opsional)' : '*' }}</label><input type="password" minlength="8" class="field" name="password" placeholder="{{ $user->exists ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter' }}" autocomplete="new-password" @required(!$user->exists)></div>
                <div><label class="label">Konfirmasi password</label><input type="password" minlength="8" class="field" name="password_confirmation" placeholder="Ulangi password" autocomplete="new-password" @required(!$user->exists)></div>
            </div>
        </section>

        <div class="contents">
            <section class="card p-6">
                <div>
                    <h3 class="section-title">Akses pengguna</h3>
                    <p class="mt-1 text-xs text-slate-400">Atur tipe pengguna, role, dan status akun.</p>
                </div>
                <div class="mt-5 space-y-5">
                    <div><label class="label">User type *</label><select class="field" name="user_type" x-model="userType">@foreach($userTypes as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div><label class="label"><span x-text="managerLabel()"></span><span x-show="managerRequired()"> *</span></label><select class="field" name="manager_id" x-model="coordinatorId" :required="managerRequired()"><option value="" x-text="'Pilih ' + managerLabel() + (managerRequired() ? '' : ' (opsional)')"></option>@foreach($managers as $m)<option value="{{ $m->id }}">{{ $m->name }}{{ $m->roleNames() ? ' · '.$m->roleNames() : '' }}</option>@endforeach</select><p class="mt-1 text-[10px] text-slate-400" x-text="managerHint()"></p></div>
                    <div>
                        <label class="label">Hak approval *</label>
                        <select class="field" name="is_approver" x-model="isApprover" required>
                            <option value="1">Bisa memberikan approval</option>
                            <option value="0">Tidak bisa memberikan approval</option>
                        </select>
                        <p class="mt-2 text-[9px] leading-relaxed text-slate-400">Nilai awal mengikuti hierarki role dan tetap dapat disesuaikan untuk akun ini.</p>
                    </div>
                    <div><label class="label">Status akun</label><select class="field" name="is_active"><option value="1" @selected(old('is_active', $user->is_active ?? 1) == 1)>Active</option><option value="0" @selected(old('is_active', $user->is_active ?? 1) == 0)>Inactive</option></select></div>
                    <div>
                        <label class="label">Perusahaan yang dapat diakses *</label>
                        <div class="space-y-2 rounded-xl border border-slate-200 p-3">
                            @foreach($tenants as $tenant)
                                <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600"><input class="size-4 accent-brand-600" type="checkbox" name="tenant_ids[]" value="{{ $tenant->id }}" @checked(in_array((int) $tenant->id, $initialTenantIds, true))>{{ $tenant->name }}</label>
                            @endforeach
                        </div>
                        <p class="mt-2 text-[9px] leading-relaxed text-slate-400">Sesudah login, pengguna hanya melihat perusahaan yang dicentang.</p>
                    </div>
                </div>
            </section>
            <div class="flex gap-3 xl:col-start-2">
                <a href="{{ route('users.index') }}" class="btn-secondary flex-1">Batal</a>
                <button class="btn-primary flex-1">Simpan</button>
            </div>
        </div>
    </div>
</form>
@endsection
