<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="theme-color" content="#185fa5">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">

@php
    $mainNav = [
        ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        [
            'route' => 'income.index',
            'label' => 'Income',
            'icon' => 'income',
            'children' => [
                ['route' => 'income.categories', 'label' => 'Categories'],
            ],
        ],
        [
            'route' => 'expense.index',
            'label' => 'Expense',
            'icon' => 'expense',
            'children' => [
                ['route' => 'budgets.index', 'label' => 'Budgeting'],
                ['route' => 'expense.categories', 'label' => 'Categories'],
            ],
        ],
        ['route' => 'reports.index', 'label' => 'Detail & Reporting', 'icon' => 'report'],
    ];
    $secondaryNav = [
        ['route' => 'savings.index', 'label' => 'Savings', 'icon' => 'savings'],
        ['route' => 'debts.index', 'label' => 'Debts', 'icon' => 'debt'],
        ['route' => 'portfolio.index', 'label' => 'Portfolio', 'icon' => 'portfolio'],
        ['route' => 'settings.index', 'label' => 'Integrations', 'icon' => 'settings'],
    ];
@endphp

<div class="min-h-full lg:flex">

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-52 -translate-x-full flex-col overflow-hidden border-r border-line bg-white transition-[transform,width] duration-150 lg:static lg:translate-x-0">
        <div class="sb-head flex h-11 shrink-0 items-center gap-2 border-b border-line px-3">
            <span class="flex h-5 w-5 shrink-0 items-center justify-center bg-accent text-[10px] font-medium text-white">F</span>
            <span class="sb-brand truncate text-[12px] font-medium tracking-tight">{{ config('app.name') }}</span>
            <button type="button" onclick="toggleSidebar()" class="ml-auto text-faint hover:text-ink lg:hidden">
                <x-icon name="close" class="h-4 w-4" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto py-2">
            <p class="sb-section lbl px-3 pb-1 pt-1">Main</p>
            @foreach ($mainNav as $item)
                @php
                    $childActive = collect($item['children'] ?? [])->contains(fn ($child) => request()->routeIs($child['route']));
                    $active = request()->routeIs($item['route']) || $childActive;
                @endphp
                <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                   class="sb-link flex items-center gap-2 border-l-2 py-[6px] pl-[10px] pr-3 text-[12px] transition
                          {{ $active
                                ? 'border-accent bg-canvas font-medium text-ink'
                                : 'border-transparent text-muted hover:border-line-strong hover:bg-canvas hover:text-ink' }}">
                    <x-icon :name="$item['icon']" class="h-3.5 w-3.5 shrink-0 {{ $active ? 'text-accent' : 'text-faint' }}" />
                    <span class="sb-label truncate">{{ $item['label'] }}</span>
                </a>

                @if (! empty($item['children']))
                    <div class="sb-subnav mb-1 ml-[26px] border-l border-line pl-2">
                        @foreach ($item['children'] as $child)
                            @php $isChildActive = request()->routeIs($child['route']); @endphp
                            <a href="{{ route($child['route']) }}"
                               class="block py-[4px] pl-1 pr-2 text-[11px] transition
                                      {{ $isChildActive ? 'font-medium text-accent' : 'text-muted hover:text-ink' }}">
                                {{ $child['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach

            <p class="sb-section lbl px-3 pb-1 pt-4">More</p>
            @foreach ($secondaryNav as $item)
                @php $active = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                   class="sb-link flex items-center gap-2 border-l-2 py-[6px] pl-[10px] pr-3 text-[12px] transition
                          {{ $active
                                ? 'border-accent bg-canvas font-medium text-ink'
                                : 'border-transparent text-muted hover:border-line-strong hover:bg-canvas hover:text-ink' }}">
                    <x-icon :name="$item['icon']" class="h-3.5 w-3.5 shrink-0 {{ $active ? 'text-accent' : 'text-faint' }}" />
                    <span class="sb-label truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach

            {{-- Sign out lives in the nav so it is reachable in both sidebar states --}}
            <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t border-line pt-2">
                @csrf
                <button type="submit" title="Sign out"
                        class="sb-link flex w-full items-center gap-2 border-l-2 border-transparent py-[6px] pl-[10px] pr-3 text-[12px] text-muted transition hover:border-line-strong hover:bg-canvas hover:text-neg">
                    <x-icon name="logout" class="h-3.5 w-3.5 shrink-0 text-faint" />
                    <span class="sb-label truncate">Sign out</span>
                </button>
            </form>
        </nav>

        <div class="border-t border-line p-2">
            <div class="sb-user flex items-center gap-2 px-1 py-1" title="{{ auth()->user()->name }}">
                <span class="flex h-5 w-5 shrink-0 items-center justify-center border border-line-strong text-[10px] font-medium text-muted">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </span>
                <div class="sb-user-meta min-w-0 flex-1 leading-tight">
                    <p class="truncate text-[11px] font-medium">{{ auth()->user()->name }}</p>
                    <p class="truncate text-[10px] text-faint">{{ auth()->user()->email }}</p>
                </div>
            </div>
        </div>
    </aside>

    <script>
        // Runs before the browser paints, so the sidebar never flashes at full width.
        (function () {
            try {
                if (localStorage.getItem('sb-collapsed') === '1') {
                    document.getElementById('sidebar').classList.add('is-collapsed');
                }
            } catch (e) {}
        })();
    </script>

    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 z-30 hidden bg-ink/20 lg:hidden"></div>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-11 shrink-0 items-center gap-2 border-b border-line bg-white px-3">
            <button type="button" onclick="toggleSidebar()" class="text-muted hover:text-ink lg:hidden">
                <x-icon name="menu" class="h-4 w-4" />
            </button>
            <button type="button" id="sb-toggle" onclick="toggleSidebarCollapse()" title="Collapse sidebar"
                    class="hidden h-6 w-6 items-center justify-center border border-line text-muted transition hover:bg-canvas hover:text-ink lg:flex">
                <x-icon name="chevron-left" id="sb-toggle-icon" class="h-3 w-3 transition-transform duration-150" />
            </button>
            <h1 class="truncate text-[13px] font-medium tracking-tight">{{ $title ?? 'Dashboard' }}</h1>
            @isset($subtitle)
                <span class="hidden truncate border-l border-line pl-2 text-[11px] text-faint sm:inline">{{ $subtitle }}</span>
            @endisset
            <div class="ml-auto flex items-center gap-1.5">
                {{ $actions ?? '' }}
            </div>
        </header>

        {{-- Children must never shrink: a flex column would otherwise squash short panels when the page is tall. --}}
        <main class="flex flex-1 flex-col px-3 py-3 sm:px-4 [&>*]:shrink-0">
            @if ($errors->any())
                <div class="mb-2 border border-neg/40 bg-neg-soft px-2.5 py-1.5 text-[11px] text-neg">
                    <p class="font-medium">Please fix the following:</p>
                    <ul class="mt-0.5 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>

    <x-modal id="confirm-modal" title="Please confirm">
        <p id="confirm-message" class="text-[12px] leading-relaxed text-muted"></p>

        <div class="mt-4 flex items-center gap-1.5 border-t border-line pt-3">
            <button type="button" id="confirm-ok" class="btn btn-primary">Confirm</button>
            <button type="button" onclick="closeModal('confirm-modal')" class="btn">Cancel</button>
        </div>
    </x-modal>

    {{-- Transient feedback: slides in bottom-right and clears itself. --}}
    <div id="toast-stack" class="pointer-events-none fixed bottom-4 right-4 z-[60] flex w-[min(20rem,calc(100vw-2rem))] flex-col-reverse gap-2">
        @if (session('status') && ! request()->boolean('created'))
            <div data-toast data-duration="2000"
                 class="pointer-events-auto flex translate-y-2 items-stretch overflow-hidden border border-line bg-white opacity-0 shadow-[0_10px_30px_-12px_rgba(15,23,42,0.35)] transition-all duration-200">
                <span class="w-[3px] shrink-0 bg-pos"></span>
                <div class="flex flex-1 items-start gap-2 px-2.5 py-2">
                    <x-icon name="check" class="mt-[1px] h-3.5 w-3.5 shrink-0 text-pos" />
                    <p class="flex-1 text-[11px] leading-relaxed text-ink">{{ session('status') }}</p>
                    <button type="button" onclick="dismissToast(this)" aria-label="Dismiss"
                            class="shrink-0 text-faint transition hover:text-ink">
                        <x-icon name="close" class="h-3 w-3" />
                    </button>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div data-toast data-duration="4500"
                 class="pointer-events-auto flex translate-y-2 items-stretch overflow-hidden border border-line bg-white opacity-0 shadow-[0_10px_30px_-12px_rgba(15,23,42,0.35)] transition-all duration-200">
                <span class="w-[3px] shrink-0 bg-neg"></span>
                <div class="flex flex-1 items-start gap-2 px-2.5 py-2">
                    <x-icon name="warning" class="mt-[1px] h-3.5 w-3.5 shrink-0 text-neg" />
                    <p class="flex-1 text-[11px] leading-relaxed text-ink">{{ session('error') }}</p>
                    <button type="button" onclick="dismissToast(this)" aria-label="Dismiss"
                            class="shrink-0 text-faint transition hover:text-ink">
                        <x-icon name="close" class="h-3 w-3" />
                    </button>
                </div>
            </div>
        @endif
    </div>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const open = !sidebar.classList.contains('-translate-x-full');
        sidebar.classList.toggle('-translate-x-full', open);
        overlay.classList.toggle('hidden', open);
    }

    function syncCollapseIcon() {
        const sidebar = document.getElementById('sidebar');
        const icon = document.getElementById('sb-toggle-icon');
        const button = document.getElementById('sb-toggle');
        const collapsed = sidebar.classList.contains('is-collapsed');

        if (icon) icon.style.transform = collapsed ? 'rotate(180deg)' : '';
        if (button) button.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
    }

    function toggleSidebarCollapse() {
        const collapsed = document.getElementById('sidebar').classList.toggle('is-collapsed');

        try {
            localStorage.setItem('sb-collapsed', collapsed ? '1' : '0');
        } catch (e) {}

        syncCollapseIcon();
    }

    syncCollapseIcon();

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        const first = modal.querySelector('input:not([type=hidden]), select, textarea');
        if (first) setTimeout(() => first.focus(), 40);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[data-modal]').forEach((modal) => modal.classList.add('hidden'));
        document.body.classList.remove('overflow-hidden');
    });

    // Centred confirmation instead of the browser's native confirm().
    window.askConfirm = function (form, message, options) {
        options = options || {};

        const ok = document.getElementById('confirm-ok');
        document.getElementById('confirm-message').textContent = message;
        ok.textContent = options.label || 'Confirm';
        ok.className = 'btn ' + (options.danger ? 'btn-destructive' : 'btn-primary');

        ok.onclick = function () {
            closeModal('confirm-modal');
            form.submit();
        };

        openModal('confirm-modal');
    };

    window.dismissToast = function (element) {
        const toast = element.closest('[data-toast]');
        if (!toast) return;

        toast.classList.add('opacity-0', 'translate-y-1');
        setTimeout(() => toast.remove(), 220);
    };

    document.querySelectorAll('[data-toast]').forEach((toast) => {
        // Let the browser paint the hidden state first so the slide-in animates.
        setTimeout(() => toast.classList.remove('opacity-0', 'translate-y-2'), 60);

        const duration = parseInt(toast.dataset.duration || '2000', 10);
        setTimeout(() => window.dismissToast(toast), duration);
    });
</script>

@stack('scripts')
</body>
</html>
