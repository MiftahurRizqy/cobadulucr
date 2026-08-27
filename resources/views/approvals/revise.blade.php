@extends('layouts.app')
@section('title', 'Perbaiki Approval')
@section('eyebrow', 'Approval · Perbaikan pengajuan')
@section('content')
@php
    $fields = \App\Models\Activity::APPROVAL_FIELDS[$activity->type];
    $details = $activity->approvalDetail;
@endphp

<form method="POST" action="{{ route('approvals.resubmit', $activity) }}" class="mx-auto max-w-4xl space-y-5">
    @csrf
    @method('PATCH')

    <section class="card overflow-hidden">
        <header class="border-b border-slate-100 px-5 py-4">
            <h3 class="section-title">{{ \App\Models\Activity::TYPES[$activity->type] }}</h3>
            <p class="mt-1 text-[10px] text-slate-400">{{ $activity->customer->company_name }}</p>
        </header>

        <div class="space-y-5 p-5">
            @if($activity->type !== 'approval_special_price')
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                <div class="text-[10px] font-black uppercase tracking-wide text-sky-700">Catatan dari approver</div>
                <div class="mt-2 whitespace-pre-line text-sm font-semibold text-slate-700">{{ $details->decision_note }}</div>
            </div>
            @endif

            <div>
                <label class="label">Judul / ringkasan *</label>
                <input class="field" name="summary" value="{{ old('summary', $activity->summary) }}" required>
                @error('summary')<p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $message }}</p>@enderror
            </div>

            @if($activity->type === 'approval_special_price')
                <div class="space-y-3">
                    @foreach($details->special_price_items ?? [] as $item)
                        @php($itemStatus = $item['status'] ?? 'revision')
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-xs font-extrabold text-ink">{{ $item['product_name'] }}</div>
                                    <div class="mt-1 text-[10px] text-slate-400">
                                        Harga saat ini Rp {{ number_format($item['normal_price'] ?? 0, 0, ',', '.') }} ·
                                        {{ number_format($item['quantity'] ?? 0, 0, ',', '.') }} {{ strtoupper($item['unit'] ?? '') }}
                                    </div>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase {{ $itemStatus === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($itemStatus === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-sky-50 text-sky-700') }}">
                                    {{ $itemStatus === 'approved' ? 'Approved' : ($itemStatus === 'rejected' ? 'Rejected' : 'Revision') }}
                                </span>
                            </div>

                            @if($item['decision_note'] ?? null)
                                <div class="mt-3 rounded-lg bg-sky-50 px-3 py-2 text-[10px] text-sky-700">
                                    Catatan approver: {{ $item['decision_note'] }}
                                </div>
                            @endif

                            @if($itemStatus === 'revision')
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="label">Harga yang diajukan *</label>
                                        <div class="relative">
                                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span>
                                            <input class="field !pl-9" type="text" inputmode="numeric" data-money name="special_price_items[{{ $item['opportunity_item_id'] }}][requested_price]" value="{{ number_format((float) old('special_price_items.'.$item['opportunity_item_id'].'.requested_price', $item['requested_price']), 0, ',', '.') }}" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="label">Alasan pengajuan *</label>
                                        <input class="field" name="special_price_items[{{ $item['opportunity_item_id'] }}][reason]" value="{{ old('special_price_items.'.$item['opportunity_item_id'].'.reason', $item['reason']) }}" required>
                                    </div>
                                </div>
                            @else
                                <div class="mt-3 text-xs text-slate-500">
                                    Harga diajukan: <span class="font-bold text-slate-700">Rp {{ number_format($item['requested_price'] ?? 0, 0, ',', '.') }}</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($fields as $key => $field)
                        @continue($key === 'unit')
                        @continue(str_starts_with($field['type'], 'computed_'))
                        @php($value = old("approval_details.$key", $details->{$key}))

                        <div @class(['md:col-span-2' => $field['type'] === 'textarea'])>
                            @if($key === 'quantity' && isset($fields['unit']))
                                <div class="grid grid-cols-[minmax(0,1fr)_120px] gap-2">
                                    <div>
                                        <label class="label">{{ $field['label'] }} *</label>
                                        <input type="number" min="0" step="1" class="field" name="approval_details[quantity]" value="{{ $value }}" required>
                                    </div>
                                    <div>
                                        <label class="label">Satuan *</label>
                                        <select class="field" name="approval_details[unit]" required>
                                            <option value="">Pilih</option>
                                            @foreach(['pcs','pack','roll','ctn','set','kg','bal'] as $unit)
                                                <option value="{{ $unit }}" @selected(old('approval_details.unit', $details->unit) === $unit)>{{ strtoupper($unit) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @elseif($field['type'] === 'textarea')
                                <label class="label">{{ $field['label'] }} *</label>
                                <textarea class="field" rows="3" name="approval_details[{{ $key }}]" required>{{ $value }}</textarea>
                            @elseif($field['type'] === 'currency')
                                <label class="label">{{ $field['label'] }} *</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span>
                                    <input type="text" inputmode="numeric" data-money class="field !pl-9" name="approval_details[{{ $key }}]" value="{{ $value !== null ? number_format((float) $value, 0, ',', '.') : '' }}" required>
                                </div>
                            @else
                                <label class="label">{{ $field['label'] }} *</label>
                                <input type="{{ $field['type'] }}" @if($field['type'] === 'number') min="0" step="1" @endif class="field" name="approval_details[{{ $key }}]" value="{{ $field['type'] === 'date' && $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : $value }}" required>
                            @endif
                            @error("approval_details.$key")<p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            @endif

            <div>
                <label class="label">Catatan tambahan</label>
                <textarea class="field" rows="3" name="detail">{{ old('detail', $activity->detail) }}</textarea>
            </div>
        </div>
    </section>

    <div class="flex justify-end gap-3">
        <a href="{{ route('approvals.index', ['status' => 'revision']) }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Ajukan ulang</button>
    </div>
</form>
@endsection
