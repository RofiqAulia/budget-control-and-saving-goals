@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();

        // Window of page numbers with ellipsis, like 1 … 4 5 6 … 20
        $pages = [];

        for ($page = 1; $page <= $last; $page++) {
            if ($page === 1 || $page === $last || abs($page - $current) <= 1) {
                $pages[] = $page;
            } elseif (end($pages) !== '…') {
                $pages[] = '…';
            }
        }
    @endphp

    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3">
        <p class="num text-[10px] text-faint">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>

        <div class="flex items-stretch">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="flex h-6 w-7 items-center justify-center border border-line bg-canvas text-faint" aria-disabled="true">
                    <x-icon name="chevron-left" class="h-3 w-3" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="flex h-6 w-7 items-center justify-center border border-line bg-white text-muted transition hover:bg-canvas hover:text-ink">
                    <x-icon name="chevron-left" class="h-3 w-3" />
                </a>
            @endif

            @foreach ($pages as $page)
                @if ($page === '…')
                    <span class="-ml-px flex h-6 w-7 items-center justify-center border border-line bg-white text-[10px] text-faint">…</span>
                @elseif ($page === $current)
                    <span aria-current="page"
                          class="-ml-px flex h-6 min-w-7 items-center justify-center border border-line-strong bg-canvas px-1.5 text-[11px] font-medium text-ink">
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $paginator->url($page) }}"
                       class="-ml-px flex h-6 min-w-7 items-center justify-center border border-line bg-white px-1.5 text-[11px] text-muted transition hover:bg-canvas hover:text-ink">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="-ml-px flex h-6 w-7 items-center justify-center border border-line bg-white text-muted transition hover:bg-canvas hover:text-ink">
                    <x-icon name="chevron-right" class="h-3 w-3" />
                </a>
            @else
                <span class="-ml-px flex h-6 w-7 items-center justify-center border border-line bg-canvas text-faint" aria-disabled="true">
                    <x-icon name="chevron-right" class="h-3 w-3" />
                </span>
            @endif
        </div>
    </nav>
@endif
