@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman" class="inline-flex overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        @if ($paginator->onFirstPage())
            <span class="grid size-9 cursor-not-allowed place-items-center border-r border-slate-200 text-slate-300" aria-disabled="true"><svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="grid size-9 place-items-center border-r border-slate-200 text-slate-500 hover:bg-slate-50" aria-label="Halaman sebelumnya"><svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="grid h-9 min-w-9 place-items-center border-r border-slate-200 px-2 text-xs text-slate-400">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="grid h-9 min-w-9 place-items-center border-r border-slate-200 bg-slate-100 px-2 text-xs font-normal text-ink" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="grid h-9 min-w-9 place-items-center border-r border-slate-200 px-2 text-xs font-normal text-slate-600 hover:bg-slate-50" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="grid size-9 place-items-center text-slate-500 hover:bg-slate-50" aria-label="Halaman berikutnya"><svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
        @else
            <span class="grid size-9 cursor-not-allowed place-items-center text-slate-300" aria-disabled="true"><svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        @endif
    </nav>
@endif
