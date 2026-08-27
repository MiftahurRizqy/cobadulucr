@extends('layouts.app')
@section('title',$role->exists?'Edit Role':'Role Baru')
@section('eyebrow','Admin / Roles')
@section('content')
@php
    $selectedParentRole = (string) old('parent_role_id', $role->parent_role_id);
    $selectedPermissions = collect(old('permission_ids', $role->exists ? $role->effectivePermissions()->pluck('id')->all() : []))->map(fn ($id) => (string) $id)->values();
    $originalPermissions = $role->exists ? $role->effectivePermissions()->pluck('id')->map(fn ($id) => (string) $id)->values() : collect();
@endphp
<form method="POST" action="{{ $role->exists?route('roles.update',$role):route('roles.store') }}"
      x-ref="roleForm" @submit="if (!confirmed) { $event.preventDefault(); reviewOpen = true }"
      x-data="{
        roleName: @js(old('name', $role->name)),
        isEdit: @js($role->exists),
        originalPermissions: @js($originalPermissions),
        parentRole: @js($selectedParentRole),
        selectedPermissions: @js($selectedPermissions),
        permissionMap: @js($inheritedPermissionMap),
        permissionDetails: @js($permissionDetails),
        roleNames: @js($roleNameMap),
        reviewOpen: false,
        deleteOpen: false,
        confirmed: false,
        get templatePermissions() { return this.parentRole ? (this.permissionMap[this.parentRole] || []) : (this.isEdit ? this.originalPermissions : []) },
        get comparisonPermissions() { return this.isEdit ? this.originalPermissions : this.templatePermissions },
        get addedPermissions() { return this.selectedPermissions.filter(id => !this.comparisonPermissions.includes(id)) },
        get removedPermissions() { return this.comparisonPermissions.filter(id => !this.selectedPermissions.includes(id)) },
        get selectedTemplateName() { return this.roleNames[this.parentRole] || (this.isEdit ? this.roleName : '') },
        applyTemplate() { this.selectedPermissions = [...this.templatePermissions] },
        hasPermission(id) { return this.selectedPermissions.includes(String(id)) },
        togglePermission(id, checked) { id = String(id); this.selectedPermissions = checked ? [...new Set([...this.selectedPermissions, id])] : this.selectedPermissions.filter(value => value !== id) },
        permissionInfo(id) { return this.permissionDetails[id] || { label: id, key: '' } },
        confirmSubmit() { this.confirmed = true; this.reviewOpen = false; this.$nextTick(() => this.$refs.roleForm.requestSubmit()) }
      }">@csrf @if($role->exists)@method('PUT')@endif
    <section class="card p-6">
        <div class="grid gap-5 md:grid-cols-2">
            <div><label class="label">Nama role *</label><input class="field" name="name" x-model="roleName" required></div>
            <div><label class="label">Template akses</label><select class="field" name="parent_role_id" x-model="parentRole" @change="applyTemplate()"><option value="">{{ $role->exists ? $role->name : 'Mulai dari awal' }}</option>@foreach($roles as $parentRole)<option value="{{ $parentRole->id }}">{{ $parentRole->name }}</option>@endforeach</select></div>
            <div class="md:col-span-2"><label class="label">Deskripsi</label><input class="field" name="description" value="{{ old('description',$role->description) }}"></div>
        </div>
    </section>
    <div class="mt-6"><div class="mb-3"><h2 class="text-sm font-extrabold text-ink">Hak akses</h2><p class="mt-1 text-xs text-slate-400">Gunakan template sebagai titik awal, lalu sesuaikan setiap hak akses sesuai kebutuhan role.</p></div><div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">@foreach($permissions as $module=>$items)<section class="card p-5"><h3 class="font-extrabold capitalize text-ink">{{ str_replace('_',' ',$module) }}</h3><div class="mt-4 space-y-2">@foreach($items as $perm)<label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition" :class="hasPermission('{{ $perm->id }}') ? 'border-brand-100 bg-brand-50/70' : 'border-slate-100 hover:bg-slate-50'"><input class="size-4 accent-brand-600" type="checkbox" name="permission_ids[]" value="{{ $perm->id }}" :checked="hasPermission('{{ $perm->id }}')" @change="togglePermission('{{ $perm->id }}', $event.target.checked)"><span class="min-w-0 flex-1"><span class="block text-sm font-semibold">{{ $perm->label }}</span><span class="text-[10px] text-slate-400">{{ $perm->key }}</span></span></label>@endforeach</div></section>@endforeach</div></div>
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">@if($role->exists && $role->slug !== 'master_admin')<button type="button" @click="deleteOpen=true" class="btn-secondary !border-rose-200 !text-rose-600 hover:!bg-rose-50">Hapus role</button>@else<span></span>@endif<div class="flex gap-3"><a href="{{ route('roles.index') }}" class="btn-secondary">Batal</a><button class="btn-primary">Tinjau & simpan</button></div></div>

    <div x-show="reviewOpen" x-cloak x-transition.opacity @keydown.escape.window="reviewOpen=false" @click.self="reviewOpen=false" class="fixed inset-0 z-[130] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
        <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h3 class="section-title">Tinjau perubahan role</h3><p class="mt-1 text-[10px] text-slate-400">Pastikan konfigurasi <strong x-text="roleName || 'role baru'"></strong> sudah sesuai.</p></div><button type="button" @click="reviewOpen=false" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200" aria-label="Tutup">×</button></div>
            <div class="overflow-y-auto p-5">
                <div class="grid gap-4 sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-4"><div class="text-[9px] font-bold uppercase text-slate-400">Total akses</div><div class="mt-1 text-xl font-black text-ink" x-text="selectedPermissions.length"></div></div><div class="rounded-xl bg-emerald-50 p-4"><div class="text-[9px] font-bold uppercase text-emerald-600">Ditambahkan</div><div class="mt-1 text-xl font-black text-emerald-700" x-text="addedPermissions.length"></div></div><div class="rounded-xl bg-rose-50 p-4"><div class="text-[9px] font-bold uppercase text-rose-600">Dicabut</div><div class="mt-1 text-xl font-black text-rose-700" x-text="removedPermissions.length"></div></div></div>
                <div x-show="!addedPermissions.length && !removedPermissions.length" class="mt-4 rounded-xl border border-slate-200 p-5 text-center text-xs text-slate-500">Tidak ada perubahan hak akses.</div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <section x-show="addedPermissions.length" class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4"><h4 class="text-xs font-extrabold text-emerald-700">Akses ditambahkan</h4><div class="mt-3 space-y-2"><template x-for="id in addedPermissions" :key="id"><div class="rounded-lg bg-white px-3 py-2"><div class="text-[11px] font-bold text-ink" x-text="permissionInfo(id).label"></div><div class="text-[9px] text-slate-400" x-text="permissionInfo(id).key"></div></div></template></div></section>
                    <section x-show="removedPermissions.length" class="rounded-xl border border-rose-200 bg-rose-50/50 p-4"><h4 class="text-xs font-extrabold text-rose-700">Akses dicabut</h4><div class="mt-3 space-y-2"><template x-for="id in removedPermissions" :key="id"><div class="rounded-lg bg-white px-3 py-2"><div class="text-[11px] font-bold text-ink" x-text="permissionInfo(id).label"></div><div class="text-[9px] text-slate-400" x-text="permissionInfo(id).key"></div></div></template></div></section>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4"><button type="button" @click="reviewOpen=false" class="btn-secondary">Kembali mengedit</button><button type="button" @click="confirmSubmit()" class="btn-primary">Konfirmasi & simpan</button></div>
        </div>
    </div>
    @if($role->exists && $role->slug !== 'master_admin')
    <div x-show="deleteOpen" x-cloak x-transition.opacity @keydown.escape.window="deleteOpen=false" @click.self="deleteOpen=false" class="fixed inset-0 z-[140] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl"><div class="p-5"><div class="grid size-11 place-items-center rounded-full bg-rose-100 text-xl font-black text-rose-600">!</div><h3 class="mt-4 text-base font-extrabold text-ink">Hapus role {{ $role->name }}?</h3><p class="mt-2 text-xs leading-relaxed text-slate-500">Role akan dilepas dari {{ $role->users()->count() }} pengguna. Role lain yang menggunakan role ini sebagai template tidak ikut dihapus.</p></div><div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4"><button type="button" @click="deleteOpen=false" class="btn-secondary">Batal</button><button type="submit" form="delete-role-form" class="btn-primary !bg-rose-600 hover:!bg-rose-700">Ya, hapus role</button></div></div>
    </div>
    @endif
</form>
@if($role->exists && $role->slug !== 'master_admin')<form id="delete-role-form" method="POST" action="{{ route('roles.destroy', $role) }}" class="hidden">@csrf @method('DELETE')</form>@endif
@endsection
