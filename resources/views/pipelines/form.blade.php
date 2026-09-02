@extends('layouts.app')
@section('title',$pipeline->exists?'Configure Pipeline':'Pipeline Baru')
@section('eyebrow','Admin / Pipeline builder')
@section('content')
<form method="POST" action="{{ $pipeline->exists?route('pipelines.update',$pipeline):route('pipelines.store') }}">@csrf @if($pipeline->exists)@method('PUT')@endif
@php
    $businessTypeOptions = $businessUnits->where('name', '!=', 'Other')->pluck('name')->values()->all();
    $currentBusinessType = old('business_type', $pipeline->business_type);
    $stageEditorData = $pipeline->exists ? $pipeline->stages->map(function ($stage) {
        $rules = $stage->rules;
        $knownRules = $rules->filter(fn ($rule) =>
            ($rule->rule_type === 'field' && $rule->field_key === 'product_name') ||
            in_array($rule->rule_type, ['file', 'follow_up', 'task'], true)
        );

        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'color' => $stage->color,
            'probability' => $stage->probability,
            'sla_days' => $stage->sla_days,
            'outcome' => $stage->is_won ? 'won' : ($stage->is_lost ? 'lost' : 'open'),
            'requirements' => [
                'product' => $rules->contains(fn ($rule) => $rule->rule_type === 'field' && $rule->field_key === 'product_name'),
                'file' => $rules->contains('rule_type', 'file'),
                'follow_up' => $rules->contains('rule_type', 'follow_up'),
                'task' => $rules->contains('rule_type', 'task'),
            ],
            'extra_rules' => $rules->whereNotIn('id', $knownRules->pluck('id'))->map(fn ($rule) => $rule->rule_type.'|'.$rule->field_key.'|'.$rule->label)->join("\n"),
        ];
    })->values() : collect();
@endphp

<section class="card p-6">
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <div><label class="label">Nama pipeline *</label><input class="field" name="name" value="{{ old('name',$pipeline->name) }}" required></div>
        <div><label class="label">Jenis customer</label><select class="field w-full" name="business_type"><option value="">Pilih jenis customer</option>@foreach($businessTypeOptions as $option)<option value="{{ $option }}" @selected($currentBusinessType === $option)>{{ $option }}</option>@endforeach</select></div>
        @if($pipeline->exists)<div><label class="label">Status</label><select class="field" name="is_active"><option value="1" @selected($pipeline->is_active)>Active</option><option value="0" @selected(!$pipeline->is_active)>Inactive</option></select></div>@endif
        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3"><input type="hidden" name="counts_as_custom_noo" value="0"><input type="checkbox" name="counts_as_custom_noo" value="1" @checked(old('counts_as_custom_noo',$pipeline->counts_as_custom_noo)) class="mt-0.5 size-4 accent-brand-600"><span><span class="block text-xs font-extrabold text-slate-700">Hitung sebagai NOO Custom</span><span class="mt-0.5 block text-[10px] leading-relaxed text-slate-400">Customer baru pada pipeline ini masuk KPI NOO Sablon/Custom.</span></span></label>
        <label class="flex items-start gap-3 rounded-xl border border-violet-200 bg-violet-50/40 p-3"><input type="hidden" name="uses_pipeline_for_custom_progress" value="0"><input type="checkbox" name="uses_pipeline_for_custom_progress" value="1" @checked(old('uses_pipeline_for_custom_progress',$pipeline->uses_pipeline_for_custom_progress)) class="mt-0.5 size-4 accent-brand-600"><span><span class="block text-xs font-extrabold text-slate-700">Alur progres produk Custom</span><span class="mt-0.5 block text-[10px] leading-relaxed text-slate-400">Tahap di pipeline ini dipakai untuk memantau produk Custom pada pipeline lain.</span></span></label>
        <div class="md:col-span-2 xl:col-span-4"><label class="label">Description</label><textarea class="field" rows="2" name="description">{{ old('description',$pipeline->description) }}</textarea></div>
    </div>
</section>

@if($pipeline->exists)
<div class="mt-6" x-data="{
    stages: @js($stageEditorData),
    addStage() { this.stages.push({ id:null, name:'Tahap baru', color:'#6366f1', probability:0, sla_days:null, outcome:'open', requirements:{ product:false, file:false, follow_up:false, task:false }, extra_rules:'' }) },
    rulesText(stage) {
        const rules = [];
        if (stage.requirements.product) rules.push('field|product_name|Produk wajib dipilih');
        if (stage.requirements.file) rules.push('file||Dokumen atau bukti wajib diunggah');
        if (stage.requirements.follow_up) rules.push('follow_up||Jadwal follow-up wajib ditentukan');
        if (stage.requirements.task) rules.push('task||Task wajib selesai');
        if (stage.extra_rules) rules.push(stage.extra_rules);
        return rules.join('\n');
    }
}">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div><h2 class="section-title">Tahapan pipeline</h2><p class="mt-1 text-xs text-slate-400">Atur urutan proses penjualan dan persyaratan setiap tahap.</p></div>
        <button type="button" @click="addStage()" class="btn-secondary">＋ Tambah tahap</button>
    </div>

    <div class="space-y-4">
        <template x-for="(stage,index) in stages" :key="stage.id || `new-${index}`">
            <article class="card overflow-hidden">
                <input type="hidden" :name="`stages[${index}][id]`" :value="stage.id">
                <input type="hidden" :name="`stages[${index}][is_won]`" :value="stage.outcome === 'won' ? 1 : 0">
                <input type="hidden" :name="`stages[${index}][is_lost]`" :value="stage.outcome === 'lost' ? 1 : 0">
                <input type="hidden" :name="`stages[${index}][rules_text]`" :value="rulesText(stage)">

                <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-[minmax(260px,1.7fr)_100px_150px_150px_minmax(190px,1fr)_40px]">
                    <div><label class="label">Nama tahap *</label><input class="field" :name="`stages[${index}][name]`" x-model="stage.name" required></div>
                    <div><label class="label">Warna</label><input type="color" class="field h-11 p-1" :name="`stages[${index}][color]`" x-model="stage.color"></div>
                    <div><label class="label">Probability</label><div class="relative"><input type="number" min="0" max="100" class="field pr-8" :name="`stages[${index}][probability]`" x-model="stage.probability"><span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400">%</span></div></div>
                    <div><label class="label">SLA Days</label><div class="relative"><input type="number" min="0" class="field pr-12" :name="`stages[${index}][sla_days]`" x-model="stage.sla_days"><span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">hari</span></div></div>
                    <div><label class="label">Outcome</label><div class="flex h-11 items-center gap-4 text-sm"><label class="flex cursor-pointer items-center gap-2"><input type="checkbox" class="rounded border-slate-300 text-emerald-600" :checked="stage.outcome === 'won'" @change="stage.outcome = $event.target.checked ? 'won' : 'open'"> Won</label><label class="flex cursor-pointer items-center gap-2"><input type="checkbox" class="rounded border-slate-300 text-rose-500" :checked="stage.outcome === 'lost'" @change="stage.outcome = $event.target.checked ? 'lost' : 'open'"> Lost</label></div></div>
                    <button type="button" @click="stages.splice(index,1)" class="mt-6 grid size-9 place-items-center rounded-full text-slate-400 transition hover:bg-rose-50 hover:text-rose-500" aria-label="Hapus tahap">✕</button>
                </div>

                <div class="border-t border-slate-100 bg-slate-50/70 px-5 py-4">
                    <div class="mb-3"><p class="text-xs font-bold text-slate-700">Syarat pindah ke tahap ini</p><p class="mt-1 text-[11px] text-slate-400">Centang data yang wajib dilengkapi sebelum opportunity dapat masuk ke tahap ini.</p></div>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-indigo-300"><input type="checkbox" class="rounded border-slate-300 text-indigo-600" x-model="stage.requirements.product"><span class="text-xs font-semibold text-slate-700">Produk sudah diisi</span></label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-indigo-300"><input type="checkbox" class="rounded border-slate-300 text-indigo-600" x-model="stage.requirements.file"><span class="text-xs font-semibold text-slate-700">Dokumen/bukti sudah diunggah</span></label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-indigo-300"><input type="checkbox" class="rounded border-slate-300 text-indigo-600" x-model="stage.requirements.follow_up"><span class="text-xs font-semibold text-slate-700">Follow-up sudah dijadwalkan</span></label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-indigo-300"><input type="checkbox" class="rounded border-slate-300 text-indigo-600" x-model="stage.requirements.task"><span class="text-xs font-semibold text-slate-700">Task sudah selesai</span></label>
                    </div>
                </div>
            </article>
        </template>
    </div>
</div>
@endif

<div class="mt-6 flex justify-end gap-3"><a href="{{ route('pipelines.index') }}" class="btn-secondary">Batal</a><button class="btn-primary">{{ $pipeline->exists?'Simpan pipeline':'Buat pipeline' }}</button></div>
</form>
@endsection
