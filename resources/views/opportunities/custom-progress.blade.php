@extends('layouts.app')
@section('title','Custom progress')
@section('eyebrow','CRM / MONITORING')
@section('page-actions')<a href="{{ route('opportunities.kanban') }}" class="btn-secondary">← Kembali ke Kanban</a>@endsection
@section('content')
<section class="card overflow-hidden">
    <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div><h2 class="section-title">Custom progress</h2><p class="mt-1 text-xs text-slate-500">Monitoring produk Custom dari seluruh pipeline.</p></div>
        <form><select name="stage" class="field !h-9 !w-48 text-xs" onchange="this.form.submit()"><option value="">Semua tahap</option>@foreach($customProgress->stages() as $stage)<option value="{{ $stage['key'] }}" @selected(request('stage')===$stage['key'])>{{ $stage['name'] }}</option>@endforeach</select></form>
    </header>
    <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-left text-xs"><thead class="table-head"><tr><th class="px-5 py-3">Produk</th><th>Customer</th><th>Pipeline</th><th>Sales</th><th>Tahap</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($items as $item)<tr><td class="px-5 py-4 font-bold"><a class="text-brand-600 hover:underline" href="{{ route('opportunities.show',$item->opportunity) }}">{{ $item->product_name }}</a></td><td>{{ $item->opportunity->customer->company_name }}</td><td>{{ $item->opportunity->pipeline->name }}</td><td>{{ $item->opportunity->owner->name }}</td><td><span class="rounded-full bg-violet-50 px-2 py-1 font-bold text-violet-700">{{ $item->progress_stage_label }}</span></td></tr>@empty<tr><td colspan="5" class="p-10 text-center text-slate-400">Belum ada produk Custom.</td></tr>@endforelse</tbody></table></div>
</section>
@endsection
