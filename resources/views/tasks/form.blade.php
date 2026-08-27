@extends('layouts.app')
@section('title', 'Task Baru')
@section('eyebrow', 'Kolaborasi / Task')

@section('content')
    <form
        method="POST"
        action="{{ route('tasks.store') }}"
        x-data="{
            taskScope: @js(old('task_scope', 'customer')),
            customerId: @js((string) old('customer_id', $task->customer_id ?? '')),
            opportunityId: @js((string) old('opportunity_id', $task->opportunity_id ?? '')),
            opportunities: @js($opportunities->map(fn ($opportunity) => [
                'id' => (string) $opportunity->id,
                'customerId' => (string) $opportunity->customer_id,
                'title' => $opportunity->title,
            ])->values()),
            get customerOpportunities() {
                if (!this.customerId) return [];
                return this.opportunities.filter(opportunity => opportunity.customerId === String(this.customerId));
            },
            syncOpportunity() {
                if (!this.customerOpportunities.some(opportunity => opportunity.id === String(this.opportunityId))) {
                    this.opportunityId = '';
                }
            },
            setTaskScope(scope) {
                this.taskScope = scope;
                if (scope === 'internal') {
                    this.customerId = '';
                    this.opportunityId = '';
                }
            },
        }"
    >
        @csrf

        <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
            <section class="card p-6">
                <h3 class="section-title">Detail task</h3>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="label">Judul task *</label>
                        <input class="field" name="title" value="{{ old('title') }}" required>
                    </div>

                    <div class="md:col-span-2">
                        <label class="label">Deskripsi</label>
                        <textarea class="field" rows="4" name="description">{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label class="label">Jenis task *</label>
                        <select class="field" name="task_scope" x-model="taskScope" @change="setTaskScope($event.target.value)">
                            <option value="customer">Terkait customer</option>
                            <option value="internal">Internal</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Reviewer memeriksa hasil task sebelum status diubah ke Menunggu Review. Wajib jika alur review digunakan.</p>
                    </div>

                    <div>
                        <label class="label">Reviewer</label>
                        <select class="field" name="reviewer_id">
                            <option value="">Pilih reviewer</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(old('reviewer_id') == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="taskScope === 'customer'" x-transition.opacity>
                        <label class="label">Customer *</label>
                        <select class="field" name="customer_id" x-model="customerId" @change="syncOpportunity()">
                            <option value="">Pilih customer</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id', $task->customer_id) == $customer->id)>
                                    {{ $customer->company_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="taskScope === 'customer'" x-transition.opacity>
                        <label class="label">Opportunity</label>
                        <select class="field disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400" name="opportunity_id" x-model="opportunityId" :disabled="!customerId">
                            <option value="" x-text="!customerId ? 'Pilih customer terlebih dahulu' : (customerOpportunities.length ? 'Tanpa opportunity' : 'Customer ini belum memiliki opportunity')"></option>
                            <template x-for="opportunity in customerOpportunities" :key="opportunity.id">
                                <option :value="opportunity.id" x-text="opportunity.title"></option>
                            </template>
                        </select>
                        <p class="mt-1 text-[10px] text-slate-400" x-show="customerId">Opsional untuk task umum customer.</p>
                    </div>

                </div>
            </section>

            <aside class="space-y-6">
                <section class="card p-6">
                    <h3 class="section-title">Penugasan</h3>

                    <div class="mt-5 space-y-4">
                        <div
                            class="relative"
                            x-data="{
                                open: false,
                                search: '',
                                selected: @js(array_map('intval', (array) old('assignee_ids', []))),
                                users: @js($users->map(fn ($user) => [
                                    'id' => $user->id,
                                    'name' => $user->name,
                                    'type' => $user->user_type,
                                    'initials' => collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode(''),
                                ])->values()),
                                get filteredUsers() {
                                    const keyword = this.search.trim().toLowerCase();
                                    return keyword
                                        ? this.users.filter(user => `${user.name} ${user.type}`.toLowerCase().includes(keyword))
                                        : this.users;
                                },
                                toggle(id) {
                                    this.selected = this.selected.includes(id)
                                        ? this.selected.filter(value => value !== id)
                                        : [...this.selected, id];
                                },
                            }"
                            @click.outside="open = false"
                        >
                            <label class="label">Dikerjakan oleh *</label>

                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="assignee_ids[]" :value="id">
                            </template>

                            <button
                                type="button"
                                class="field flex min-h-11 w-full items-center justify-between gap-3 text-left"
                                @click="open = !open"
                                :aria-expanded="open"
                            >
                                <span class="min-w-0 flex-1 truncate" :class="selected.length ? 'text-ink' : 'text-slate-400'">
                                    <span x-text="selected.length ? `${selected.length} orang dipilih` : 'Pilih orang'">Pilih orang</span>
                                </span>
                                <svg class="size-4 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="m6 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>

                            <div
                                x-cloak
                                x-show="open"
                                x-transition.origin.top
                                class="absolute right-0 z-40 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                            >
                                <div class="border-b border-slate-100 p-3">
                                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 focus-within:border-primary">
                                        <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <circle cx="9" cy="9" r="5.5"/><path d="m13 13 4 4" stroke-linecap="round"/>
                                        </svg>
                                        <input
                                            x-model="search"
                                            type="search"
                                            class="min-w-0 flex-1 border-0 bg-transparent py-2.5 text-sm outline-none"
                                            placeholder="Cari nama..."
                                            @click.stop
                                        >
                                    </div>
                                </div>

                                <div class="max-h-64 overflow-y-auto p-2">
                                    <template x-for="user in filteredUsers" :key="user.id">
                                        <label class="flex cursor-pointer items-center gap-3 rounded-lg px-2.5 py-2 transition hover:bg-slate-50">
                                            <input
                                                type="checkbox"
                                                class="size-4 rounded border-slate-300 text-primary focus:ring-primary"
                                                :checked="selected.includes(user.id)"
                                                @change="toggle(user.id)"
                                            >
                                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-indigo-50 text-[10px] font-bold text-primary" x-text="user.initials"></span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-semibold text-ink" x-text="user.name"></span>
                                                <span class="block text-[10px] capitalize text-slate-400" x-text="user.type"></span>
                                            </span>
                                        </label>
                                    </template>

                                    <p x-show="filteredUsers.length === 0" class="px-3 py-6 text-center text-xs text-slate-400">
                                        Nama tidak ditemukan.
                                    </p>
                                </div>

                                <div class="flex items-center justify-between border-t border-slate-100 px-3 py-2.5">
                                    <span class="text-[11px] text-slate-500" x-text="`${selected.length} orang dipilih`"></span>
                                    <button type="button" class="text-xs font-semibold text-primary" @click="open = false">Selesai</button>
                                </div>
                            </div>

                            @error('assignee_ids')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="label">Batas waktu</label>
                            <input type="datetime-local" class="field" name="due_at" value="{{ old('due_at') }}">
                        </div>

                        <div>
                            <label class="label">Prioritas</label>
                            <select class="field" name="priority">
                                @foreach (\App\Support\Crm::PRIORITIES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('priority', 'medium') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <div class="flex gap-3">
                    <a href="{{ route('tasks.index') }}" class="btn-secondary flex-1">Batal</a>
                    <button class="btn-primary flex-1">Simpan task</button>
                </div>
            </aside>
        </div>
    </form>
@endsection
