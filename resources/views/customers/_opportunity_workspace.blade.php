<section class="card flex-1 overflow-visible">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div>
            <h3 class="section-title">Opportunity customer</h3>
            <p class="mt-1 text-xs text-slate-400">Ringkasan kebutuhan dan tahap penjualan customer.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($opportunityOptions->count() > 1)
                <select class="input h-9 min-w-56 cursor-pointer py-0 text-xs font-bold" aria-label="Pilih opportunity" onchange="if(this.value) window.location.href=this.value">
                    @foreach($opportunityOptions as $option)
                        <option value="{{ route('customers.show', ['customer' => $customer, 'opportunity' => $option->id]) }}" @selected($selectedOpportunity?->id === $option->id)>{{ $option->title }} · {{ $option->stage?->name ?? 'Tanpa tahap' }}</option>
                    @endforeach
                </select>
            @endif
            <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-bold">
                <span class="badge bg-violet-50 text-violet-700">{{ $opportunityStats['total'] }} opportunity</span>
                <span class="badge bg-amber-50 text-amber-700">{{ $opportunityStats['processing'] }} diproses</span>
                <span class="badge bg-emerald-50 text-emerald-700">{{ $opportunityStats['deal'] }} deal</span>
            </div>
            @if($opportunityOptions->isNotEmpty())
                <a href="{{ route('opportunities.index', ['customer' => $customer->id]) }}" class="btn-secondary h-9 px-3 text-[10px]">Lihat semua</a>
            @endif
        </div>
    </header>

    <div class="divide-y divide-slate-100">
        @php
            $displayOpportunities = $selectedOpportunity ? collect([$selectedOpportunity]) : collect();
        @endphp
        @forelse($displayOpportunities as $opportunity)
            @php
                $visibleItems = $opportunity->items->take(3);
                $remainingItems = max(0, $opportunity->items->count() - $visibleItems->count());
                $pipelineStages = $opportunity->pipeline?->stages
                    ?->filter(fn ($stage) => $stage->is_active || $stage->id === $opportunity->pipeline_stage_id)
                    ->values() ?? collect();
                $currentStageIndex = $pipelineStages->search(fn ($stage) => $stage->id === $opportunity->pipeline_stage_id);
            @endphp

            <details class="group" x-data="{ productsOpen: false }" open>
                <summary class="flex cursor-default list-none items-center gap-4 px-5 py-4" @click.prevent>
                    <span class="size-2.5 shrink-0 rounded-full" style="background:{{ $opportunity->stage?->color ?? '#64748b' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate text-sm font-extrabold text-ink">{{ $opportunity->title }}</span>
                            <span class="badge bg-slate-100 text-slate-500">{{ $opportunity->opportunity_id }}</span>
                        </div>
                        <div class="mt-1.5 flex min-w-0 flex-wrap items-center gap-1.5">
                            @forelse($visibleItems as $item)
                                <span class="max-w-52 truncate rounded-md bg-sky-50 px-2 py-1 text-[10px] font-semibold text-sky-700">{{ $item->product_name }} &middot; {{ number_format($item->quantity, 0, ',', '.') }} {{ strtoupper($item->quantity_unit) }}</span>
                            @empty
                                <span class="text-[10px] text-slate-400">Produk belum diisi</span>
                            @endforelse
                            @if($remainingItems)
                                <button type="button" @click.stop="productsOpen=true" class="rounded-md bg-brand-50 px-2 py-1 text-[10px] font-bold text-brand-600 transition hover:bg-brand-100">+{{ $remainingItems }} produk lainnya</button>
                            @endif
                        </div>
                    </div>
                    <div class="hidden shrink-0 text-right sm:block">
                        <div class="text-sm font-extrabold text-ink">Rp {{ number_format($opportunity->estimated_value, 0, ',', '.') }}</div>
                        <div class="mt-1 text-[10px] text-slate-400">{{ $opportunity->stage?->name ?? 'Tanpa tahap' }} &middot; {{ $opportunity->probability }}%</div>
                    </div>
                </summary>

                <div class="border-t border-slate-100 bg-slate-50/50 px-5 py-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div><div class="label">Pipeline</div><div class="mt-1 truncate text-xs font-bold text-slate-700">{{ $opportunity->pipeline?->name ?? '—' }}</div></div>
                        <div><div class="label">Target closing</div><div class="mt-1 text-xs font-bold text-slate-700">{{ $opportunity->expected_close_date?->translatedFormat('d M Y') ?? 'Belum ditentukan' }}</div></div>
                        <div><div class="label">Next action</div><div class="mt-1 line-clamp-2 text-xs font-bold text-slate-700">{{ $opportunity->next_action ?: 'Belum diisi' }}</div></div>
                    </div>

                    @if($pipelineStages->isNotEmpty())
                        <div class="mt-4">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <div class="label">Tahap penjualan</div>
                                <div class="text-[10px] text-slate-400">Klik tahap untuk memindahkan</div>
                            </div>
                            <div class="overflow-x-auto pb-1">
                                <div class="flex min-w-max items-stretch pr-3">
                                    @foreach($pipelineStages as $stageIndex => $stage)
                                        @php
                                            $isCurrentStage = $stage->id === $opportunity->pipeline_stage_id;
                                            $isPassedStage = $currentStageIndex !== false && $stageIndex < $currentStageIndex;
                                            $arrowStyle = $loop->first
                                                ? 'clip-path:polygon(0 0,calc(100% - 12px) 0,100% 50%,calc(100% - 12px) 100%,0 100%);'
                                                : 'clip-path:polygon(0 0,calc(100% - 12px) 0,100% 50%,calc(100% - 12px) 100%,0 100%,12px 50%);';
                                        @endphp
                                        <form method="POST" action="{{ route('opportunities.stage', $opportunity) }}" class="{{ $loop->first ? '' : '-ml-1' }}">
                                            @csrf
                                            <input type="hidden" name="stage_id" value="{{ $stage->id }}">
                                            <button type="submit"
                                                class="h-10 min-w-32 px-5 text-center text-[10px] font-extrabold transition {{ $isCurrentStage ? 'bg-brand-600 text-white' : ($isPassedStage ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-brand-100 hover:text-brand-700') }}"
                                                style="{{ $arrowStyle }}"
                                                title="{{ $isCurrentStage ? 'Tahap saat ini' : 'Pindahkan ke '.$stage->name }}"
                                                @if($isCurrentStage) disabled aria-current="step" @endif>
                                                {{ $stage->name }}
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="mt-3 text-right"><a href="{{ route('opportunities.show', $opportunity) }}" class="text-xs font-bold text-brand-600">Buka detail opportunity &rarr;</a></div>
                </div>

                <div x-show="productsOpen" x-cloak x-transition.opacity @keydown.escape.window="productsOpen=false" @click.self="productsOpen=false" class="fixed inset-0 z-[120] grid place-items-center bg-slate-950/60 p-4 backdrop-blur-sm">
                    <div class="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div><h3 class="section-title">Daftar produk</h3><p class="mt-1 text-[10px] text-slate-400">{{ $opportunity->title }} &middot; {{ $opportunity->items->count() }} produk</p></div>
                            <button type="button" @click="productsOpen=false" class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-500 transition hover:bg-slate-200" aria-label="Tutup"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3l10 10M13 3L3 13"/></svg></button>
                        </header>
                        <div class="overflow-y-auto p-5">
                            <div class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                                @foreach($opportunity->items as $item)
                                    <div class="flex items-center justify-between gap-4 px-4 py-3">
                                        <div class="min-w-0"><div class="truncate text-xs font-bold text-ink">{{ $item->product_name }}</div><div class="mt-1 text-[10px] text-slate-400">{{ number_format($item->quantity, 0, ',', '.') }} {{ strtoupper($item->quantity_unit) }}</div></div>
                                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold {{ $item->deal_status === 'deal' ? 'bg-emerald-50 text-emerald-700' : ($item->deal_status === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">{{ $item->deal_status === 'deal' ? 'Deal' : ($item->deal_status === 'rejected' ? 'Ditolak' : 'Diproses') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <footer class="flex justify-end border-t border-slate-100 bg-slate-50 px-5 py-4"><button type="button" @click="productsOpen=false" class="btn-secondary">Tutup</button></footer>
                    </div>
                </div>
            </details>
        @empty
            <div class="p-10 text-center"><div class="text-sm font-bold text-slate-600">Belum ada opportunity</div><div class="mt-1 text-xs text-slate-400">Buat opportunity saat kebutuhan customer sudah jelas.</div></div>
        @endforelse
    </div>

</section>
