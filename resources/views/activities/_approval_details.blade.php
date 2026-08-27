@php
    $approvalFields = \App\Models\Activity::APPROVAL_FIELDS[$activity->type] ?? [];
    $approvalDetails = $activity->approvalDetail?->toArray() ?? [];
    $specialPriceItems = $activity->approvalDetail?->special_price_items ?? [];
    $initialItemDecisions = collect($specialPriceItems)->mapWithKeys(function ($item) {
        $status = $item['status'] ?? 'pending';

        return [(string) $item['opportunity_item_id'] => [
            'decision' => $status === 'pending' ? 'approved' : $status,
            'note' => $item['decision_note'] ?? '',
        ]];
    })->all();
@endphp

@if($approvalFields && $approvalDetails)
    @php
        $approvalStatus = $activity->approvalDetail->approval_status ?? 'pending';
        $statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'revision' => 'Revision Required', 'rejected' => 'Rejected'];
        $statusClasses = ['pending' => 'bg-amber-100 text-amber-700', 'approved' => 'bg-emerald-100 text-emerald-700', 'revision' => 'bg-sky-100 text-sky-700', 'rejected' => 'bg-rose-100 text-rose-700'];
        $canDecide = $approvalStatus === 'pending'
            && auth()->user()->canApprove()
            && (int) auth()->id() !== (int) $activity->user_id;
        $approvalCaptcha = $canDecide ? (string) random_int(10000, 99999) : null;
        $approvalCaptchaImage = $canDecide ? \App\Support\ApprovalCaptcha::imageDataUri($approvalCaptcha) : null;
        $approvalCaptchaToken = $canDecide ? \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
            'activity_id' => $activity->id,
            'code' => $approvalCaptcha,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ])) : null;
    @endphp
    <section x-data="{
        decisionNote: @js(old('decision_note', '')),
        authenticationOpen: @js($errors->has('captcha_answer') && (int) old('activity_id') === (int) $activity->id),
        selectedDecision: 'approved',
        itemDecisions: @js($initialItemDecisions),
        captchaImage: @js($approvalCaptchaImage),
        captchaToken: @js($approvalCaptchaToken),
        captchaLoading: false,
        async refreshCaptcha() {
            this.captchaLoading = true;
            try {
                const response = await fetch(@js(route('approvals.captcha', $activity)), { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('CAPTCHA refresh failed');
                const challenge = await response.json();
                this.captchaImage = challenge.image;
                this.captchaToken = challenge.token;
                this.$refs.captchaAnswer.value = '';
                this.$refs.captchaAnswer.focus();
            } finally {
                this.captchaLoading = false;
            }
        }
    }" class="mt-4 overflow-hidden rounded-xl border border-violet-200 bg-white">
        <header class="flex items-center justify-between gap-3 border-b border-violet-100 bg-violet-50 px-4 py-3">
            <h4 class="text-[11px] font-extrabold uppercase tracking-wide text-violet-700">Detail pengajuan</h4>
            @if($activity->type !== 'approval_special_price')
                <span class="rounded-full px-2.5 py-1 text-[9px] font-extrabold {{ $statusClasses[$approvalStatus] ?? $statusClasses['pending'] }}">{{ $statusLabels[$approvalStatus] ?? $approvalStatus }}</span>
            @endif
        </header>
        @if($activity->type === 'approval_special_price' && $specialPriceItems)
            <div class="divide-y divide-slate-100">
                @foreach($specialPriceItems as $item)
                    @php
                        $itemKey = (string) $item['opportunity_item_id'];
                    @endphp
                    <div class="p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><div class="text-sm font-extrabold text-ink">{{ $item['product_name'] }}</div><div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-medium text-slate-500"><span>{{ number_format($item['quantity'],0,',','.') }} {{ strtoupper($item['unit']) }}</span><span class="text-slate-300">•</span><span>Harga saat ini <strong class="font-bold text-slate-600">Rp {{ number_format($item['normal_price'],0,',','.') }}</strong></span><span class="text-slate-300">•</span><span>Target <strong class="font-bold text-slate-600">Rp {{ number_format($item['target_price'],0,',','.') }}</strong></span></div></div><div class="text-right"><div class="text-[9px] font-bold uppercase text-violet-500">Harga diajukan</div><div class="text-base font-extrabold text-violet-700">Rp {{ number_format($item['requested_price'],0,',','.') }}</div></div></div>
                        <div class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-[10px] text-slate-600">{{ $item['reason'] }}</div>
                        @if($canDecide)
                            <div class="mt-3 grid gap-2 sm:grid-cols-[180px_1fr]">
                                <select class="field" x-model="itemDecisions[@js($itemKey)].decision"><option value="approved">Approve</option><option value="revision">Request Revision</option><option value="rejected">Reject</option></select>
                                <input class="field" x-model="itemDecisions[@js($itemKey)].note" placeholder="Catatan approver">
                            </div>
                        @else
                            @php
                                $itemStatus = $item['status'] ?? 'pending';
                                $itemStatusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'revision' => 'Revision Required', 'rejected' => 'Rejected'];
                                $itemActorLabels = ['pending' => 'Assigned to', 'approved' => 'Approved by', 'revision' => 'Revision requested by', 'rejected' => 'Rejected by'];
                                $itemStatusClass = $itemStatus === 'approved' ? 'bg-emerald-50 text-emerald-600' : ($itemStatus === 'rejected' ? 'bg-rose-50 text-rose-600' : ($itemStatus === 'revision' ? 'bg-sky-50 text-sky-600' : 'bg-amber-50 text-amber-600'));
                                $decidedBy = $activity->approvalDetail->decidedBy;
                            @endphp
                            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Decision</div>
                                        <span class="badge mt-1 {{ $itemStatusClass }}">{{ $itemStatusLabels[$itemStatus] ?? ucfirst($itemStatus) }}</span>
                                    </div>
                                    @if($decidedBy)
                                        <div class="text-right">
                                            <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">{{ $itemActorLabels[$itemStatus] ?? 'Decided by' }}</div>
                                            <div class="mt-1 text-[11px] font-extrabold text-slate-700">{{ $decidedBy->name }}</div>
                                            @if($activity->approvalDetail->decided_at)<div class="mt-0.5 text-[9px] text-slate-400">{{ $activity->approvalDetail->decided_at->translatedFormat('d M Y, H:i') }}</div>@endif
                                        </div>
                                    @endif
                                </div>
                                @if($item['decision_note'] ?? null)
                                    <div class="mt-3 border-t border-slate-200 pt-3">
                                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Catatan approver</div>
                                        <div class="mt-1 whitespace-pre-line text-[11px] font-semibold text-slate-700">{{ $item['decision_note'] }}</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
        <dl class="grid md:grid-cols-2">
            @foreach($approvalFields as $key => $field)
                @php
                    $value = $approvalDetails[$key] ?? null;
                    if (in_array($field['type'], ['currency', 'computed_currency'], true) && is_numeric($value)) $value = 'Rp '.number_format((float) $value, 0, ',', '.');
                    if (in_array($key, ['current_days', 'requested_days', 'additional_days'], true) && is_numeric($value)) $value = number_format((float) $value, 0, ',', '.').' hari';
                    if ($field['type'] === 'date' && $value) $value = \Illuminate\Support\Carbon::parse($value)->translatedFormat('d M Y');
                    if ($field['type'] === 'unit' && $value) $value = strtoupper($value);
                @endphp
                <div @class(['border-b border-slate-100 px-4 py-3', 'md:col-span-2' => $field['type'] === 'textarea'])>
                    <dt class="text-[9px] font-bold uppercase tracking-wide text-slate-400">{{ $field['label'] }}</dt>
                    <dd class="mt-1 whitespace-pre-line text-xs font-semibold text-slate-700">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>
        @endif
        @if($activity->type !== 'approval_special_price' && $activity->approvalDetail->decision_note)
            <div class="border-t border-slate-100 bg-slate-50 px-4 py-3"><div class="text-[9px] font-bold uppercase text-slate-400">Catatan keputusan</div><div class="mt-1 whitespace-pre-line text-xs font-semibold text-slate-700">{{ $activity->approvalDetail->decision_note }}</div></div>
        @endif
        @if($approvalStatus === 'revision' && (auth()->user()->isMasterAdmin() || (int) auth()->id() === (int) $activity->user_id))
            <div class="flex items-center justify-between gap-3 border-t border-sky-100 bg-sky-50 p-4">
                <div><div class="text-xs font-extrabold text-sky-800">Pengajuan perlu diperbaiki</div><div class="mt-1 text-[10px] text-sky-600">Perbarui data sesuai catatan keputusan, lalu ajukan kembali.</div></div>
                <a href="{{ route('approvals.revise', $activity) }}" class="btn-primary shrink-0">Perbaiki & ajukan ulang</a>
            </div>
        @endif
        @if($canDecide)
            <div class="border-t border-violet-100 bg-violet-50/40 p-4">
                @if($activity->type !== 'approval_special_price')
                    <label class="label">Catatan approver</label>
                    <textarea x-model="decisionNote" class="field" rows="2" placeholder="Catatan approver"></textarea>
                @endif
                <div class="mt-3 flex flex-wrap justify-end gap-2">
                    @if($activity->type === 'approval_special_price')
                        <button type="button" :disabled="Object.values(itemDecisions).some(item => !String(item.note || '').trim())" @click="authenticationOpen = true" class="h-9 rounded-lg bg-violet-600 px-3 text-[10px] font-extrabold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-50">Simpan keputusan</button>
                    @else
                    @foreach(['rejected' => 'Reject', 'revision' => 'Request Revision', 'approved' => 'Approve'] as $decision => $label)
                        @if($decision !== 'approved')
                            <form method="POST" action="{{ route('activities.approval.decision', $activity) }}">
                                @csrf
                                <input type="hidden" name="decision" value="{{ $decision }}">
                                <input type="hidden" name="decision_note" :value="decisionNote">
                                <button type="submit" @class([
                                    'h-9 rounded-lg border bg-white px-3 text-[10px] font-extrabold shadow-sm',
                                    'border-rose-200 text-rose-600' => $decision === 'rejected',
                                    'border-sky-200 text-sky-600' => $decision === 'revision',
                                ])>{{ $label }}</button>
                            </form>
                        @else
                            <button type="button" @click="authenticationOpen = true" class="h-9 rounded-lg bg-violet-600 px-3 text-[10px] font-extrabold text-white shadow-sm">{{ $label }}</button>
                        @endif
                    @endforeach
                    @endif
                </div>
            </div>

            <template x-teleport="body">
                <div x-show="authenticationOpen" x-cloak @keydown.escape.window="authenticationOpen=false" @click.self="authenticationOpen=false" class="fixed inset-0 z-[160] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
                    <form method="POST" action="{{ route('activities.approval.decision', $activity) }}" class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                        @csrf
                        <input type="hidden" name="activity_id" value="{{ $activity->id }}">
                        <input type="hidden" name="decision" :value="selectedDecision">
                        <input type="hidden" name="decision_note" :value="decisionNote">
                        <input type="hidden" name="captcha_token" :value="captchaToken">
                        <template x-for="(item, itemId) in itemDecisions" :key="itemId"><span><input type="hidden" :name="`item_decisions[${itemId}][decision]`" :value="item.decision"><input type="hidden" :name="`item_decisions[${itemId}][note]`" :value="item.note"></span></template>
                        <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                            <div><h3 class="text-sm font-extrabold text-ink">Confirm Approval</h3><p class="mt-1 text-xs text-slate-500">Enter the CAPTCHA shown below to authorize this approval.</p></div>
                            <button type="button" @click="authenticationOpen=false" class="grid size-10 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200 hover:text-slate-700" aria-label="Close modal"><svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button>
                        </header>
                        <div class="space-y-4 p-5">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Pengajuan</div><div class="mt-1 text-xs font-bold text-slate-700">{{ $activity->summary }}</div></div>
                            <div class="relative overflow-hidden rounded-xl border border-violet-300 bg-violet-50 px-4 py-5 text-center">
                                <div class="relative flex items-center justify-center gap-2 text-[9px] font-bold uppercase tracking-[0.18em] text-violet-500">CAPTCHA <button type="button" @click="refreshCaptcha()" :disabled="captchaLoading" class="rounded-md bg-white px-2 py-1 tracking-normal text-violet-600 shadow-sm disabled:opacity-50" x-text="captchaLoading ? 'Loading…' : 'Refresh'"></button></div>
                                <img :src="captchaImage" alt="CAPTCHA verification image" class="relative mx-auto mt-2 h-[92px] w-full max-w-[300px] select-none rounded-xl object-cover" draggable="false">
                                <div class="relative mt-2 text-[9px] text-violet-500">Enter the five digits shown · valid for 10 minutes</div>
                            </div>
                            <div><label class="label" for="approval-captcha-{{ $activity->id }}">Enter CAPTCHA</label><input x-ref="captchaAnswer" id="approval-captcha-{{ $activity->id }}" name="captcha_answer" type="text" inputmode="numeric" pattern="[0-9]{5}" maxlength="5" autocomplete="off" spellcheck="false" class="field text-center font-mono text-lg font-bold tracking-[0.08em]" required autofocus placeholder="Enter the five digits">@if($errors->has('captcha_answer') && (int) old('activity_id') === (int) $activity->id)<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $errors->first('captcha_answer') }}</p>@endif</div>
                        </div>
                        <footer class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4"><button type="button" @click="authenticationOpen=false" class="btn-secondary">Cancel</button><button type="submit" class="btn-primary">Confirm Approval</button></footer>
                    </form>
                </div>
            </template>
        @endif
    </section>
@endif
