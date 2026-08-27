@props([
    'name',
    'options' => [],
    'selected' => '',
    'placeholder' => 'Pilih',
])

<div
    x-data="{ open: false, value: @js((string) $selected), label: @js((string) ($options[(string) $selected] ?? $placeholder)) }"
    @click.outside="open=false"
    @keydown.escape.window="open=false"
    class="relative"
>
    <input type="hidden" name="{{ $name }}" :value="value">
    <button
        type="button"
        class="field flex w-full items-center justify-between gap-3 text-left text-sm"
        @click="open=!open"
        :aria-expanded="open"
    >
        <span class="min-w-0 truncate" x-text="label"></span>
        <svg class="size-4 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 7.5 5 5 5-5"/></svg>
    </button>
    <div
        x-show="open"
        x-cloak
        x-transition.origin.top
        class="scrollbar-thin absolute left-0 right-0 top-full z-[70] mt-1 max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl"
    >
        <button
            type="button"
            class="flex w-full items-center rounded-lg px-3 py-2 text-left text-xs transition hover:bg-slate-50"
            :class="value === '' ? 'bg-brand-50 font-bold text-brand-600' : 'text-slate-600'"
            @click="value=''; label=@js($placeholder); open=false"
        >{{ $placeholder }}</button>
        @foreach($options as $value => $label)
            <button
                type="button"
                class="flex w-full items-center rounded-lg px-3 py-2 text-left text-xs transition hover:bg-slate-50"
                :class="value === @js((string) $value) ? 'bg-brand-50 font-bold text-brand-600' : 'text-slate-600'"
                @click="value=@js((string) $value); label=@js((string) $label); open=false"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
