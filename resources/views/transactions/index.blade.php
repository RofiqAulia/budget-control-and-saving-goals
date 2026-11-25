@php
    $isIncome = $type === 'income';
    $label = ucfirst($type);
    $hasPeriod = request()->filled('period');

    // Header links keep the current filters and flip the direction when already sorted.
    $sortLink = function (string $column) use ($sort, $dir, $type, $hasPeriod, $period) {
        $next = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';

        return route($type.'.index', array_filter([
            'period' => $hasPeriod ? $period : null,
            'q' => request('q'),
            'category' => request('category'),
            'sort' => $column,
            'dir' => $next,
        ], fn ($value) => $value !== null && $value !== ''));
    };

    $arrow = fn (string $column) => $sort === $column ? ($dir === 'asc' ? '▲' : '▼') : '';
@endphp

<x-app-layout :title="$label" :subtitle="$hasPeriod ? \Illuminate\Support\Carbon::parse($period.'-01')->format('M Y') : 'All months'">
    <x-slot:actions>
        <x-period-picker :period="$period" :route="$type . '.index'" :allow-all="true" />
        <button type="button" onclick="openTxModal()" class="btn btn-primary">
            <x-icon name="plus" class="h-3 w-3" /> Add {{ strtolower($label) }}
        </button>
    </x-slot:actions>

    <div class="panel">
        <div class="flex flex-wrap items-center gap-2 border-b border-line bg-canvas px-2.5 py-2">
            <div class="flex items-baseline gap-1.5">
                <span class="lbl">{{ $label }} total</span>
                <span id="tx-total" class="num text-[13px] font-medium {{ $isIncome ? 'text-pos' : 'text-neg' }}">@money($filteredTotal)</span>
                <span id="tx-count" class="num text-[10px] text-faint">({{ $transactions->total() }})</span>
            </div>

            <form id="tx-filters" method="GET" action="{{ route($type.'.index') }}" class="ml-auto flex flex-wrap items-center gap-1.5">
                <input type="hidden" name="period" value="{{ $hasPeriod ? $period : '' }}">

                <input name="q" value="{{ request('q') }}" autocomplete="off"
                       placeholder="Search all columns…" class="field w-52 py-[3px] text-[11px]">

                <select name="category" class="field w-auto cursor-pointer py-[3px] text-[11px]">
                    <option value="">All categories</option>
                    @foreach ($categories as $group)
                        @foreach ($group->children as $child)
                            <option value="{{ $child->id }}" @selected(request('category') == $child->id)>{{ $group->name }} / {{ $child->name }}</option>
                        @endforeach
                    @endforeach
                </select>

                <button class="btn py-[3px]">Filter</button>

                @if (request()->filled('q') || request()->filled('category'))
                    <a href="{{ route($type.'.index', $hasPeriod ? ['period' => $period] : []) }}"
                       class="px-1 text-[10px] text-muted hover:text-ink">Reset</a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="th w-24">
                            <a href="{{ $sortLink('date') }}" data-sort-link class="flex w-full items-center gap-1 transition hover:text-ink">
                                Date<span class="text-[9px] text-accent" data-sort-indicator="date">{{ $arrow('date') }}</span>
                            </a>
                        </th>
                        <th class="th">
                            <a href="{{ $sortLink('group') }}" data-sort-link class="flex w-full items-center gap-1 transition hover:text-ink">
                                Group<span class="text-[9px] text-accent" data-sort-indicator="group">{{ $arrow('group') }}</span>
                            </a>
                        </th>
                        <th class="th">
                            <a href="{{ $sortLink('category') }}" data-sort-link class="flex w-full items-center gap-1 transition hover:text-ink">
                                Category<span class="text-[9px] text-accent" data-sort-indicator="category">{{ $arrow('category') }}</span>
                            </a>
                        </th>
                        <th class="th hidden sm:table-cell">
                            <a href="{{ $sortLink('description') }}" data-sort-link class="flex w-full items-center gap-1 transition hover:text-ink">
                                Note<span class="text-[9px] text-accent" data-sort-indicator="description">{{ $arrow('description') }}</span>
                            </a>
                        </th>
                        <th class="th hidden md:table-cell">
                            <a href="{{ $sortLink('source') }}" data-sort-link class="flex w-full items-center gap-1 transition hover:text-ink">
                                Source<span class="text-[9px] text-accent" data-sort-indicator="source">{{ $arrow('source') }}</span>
                            </a>
                        </th>
                        <th class="th text-right">
                            <a href="{{ $sortLink('amount') }}" data-sort-link class="flex w-full items-center justify-end gap-1 transition hover:text-ink">
                                Amount<span class="text-[9px] text-accent" data-sort-indicator="amount">{{ $arrow('amount') }}</span>
                            </a>
                        </th>
                        <th class="th w-16"></th>
                    </tr>
                </thead>
                <tbody id="tx-rows">
                    @include('transactions._rows', ['transactions' => $transactions, 'type' => $type, 'isIncome' => $isIncome])
                </tbody>
            </table>
        </div>

        <div id="tx-pagination" class="border-t border-line px-3 py-2 {{ $transactions->hasPages() ? '' : 'hidden' }}">
            {{ $transactions->links('pagination.ledger') }}
        </div>
    </div>

    <x-modal id="tx-modal" :title="'Add ' . strtolower($label)">
        <form id="tx-form" method="POST" action="{{ route($type.'.store') }}">
            @csrf
            <input type="hidden" name="_method" value="POST" id="tx-method">
            @include('transactions._form', [
                'transaction' => new \App\Models\Transaction(['type' => $type, 'date' => now()]),
                'submitLabel' => 'Save ' . strtolower($label),
                'cancelAsButton' => true,
                'modalId' => 'tx-modal',
            ])
        </form>
    </x-modal>

    <x-modal id="saved-modal" title="Saved">
        <p class="text-[12px] leading-relaxed text-muted">
            Your {{ strtolower($label) }} was recorded. What would you like to do next?
        </p>

        <div class="mt-4 flex items-center gap-1.5 border-t border-line pt-3">
            <button type="button" class="btn btn-primary" onclick="closeModal('saved-modal'); openTxModal();">
                Add another
            </button>
            <button type="button" class="btn" onclick="closeModal('saved-modal')">Back to list</button>
        </div>
    </x-modal>

    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('tx-form');
                const method = document.getElementById('tx-method');
                const title = document.querySelector('#tx-modal [data-modal-title]');
                const submit = form.querySelector('.btn-primary');
                const storeAction = @json(route($type . '.store'));
                const addTitle = @json('Add ' . strtolower($label));
                const editTitle = @json('Edit ' . strtolower($label));
                const addSubmit = @json('Save ' . strtolower($label));
                const editSubmit = @json('Update ' . strtolower($label));

                window.openTxModal = function (button) {
                    form.reset();
                    const editing = button && button.dataset.id;

                    if (editing) {
                        form.action = button.dataset.action;
                        method.value = 'PUT';
                        form.querySelector('[name="category_id"]').value = button.dataset.category;
                        form.querySelector('[name="amount"]').value = button.dataset.amount;
                        form.querySelector('[name="date"]').value = button.dataset.date;
                        form.querySelector('[name="description"]').value = button.dataset.description;
                        title.textContent = editTitle;
                        submit.textContent = editSubmit;
                    } else {
                        form.action = storeAction;
                        method.value = 'POST';
                        form.querySelector('[name="date"]').value = @json(now()->toDateString());
                        title.textContent = addTitle;
                        submit.textContent = addSubmit;
                    }

                    openModal('tx-modal');
                };

                // Editing asks for confirmation before writing.
                form.addEventListener('submit', function (event) {
                    if (method.value !== 'PUT') return;

                    event.preventDefault();
                    askConfirm(form, 'Save the changes to this entry?', { label: 'Save changes' });
                });

                if (new URLSearchParams(window.location.search).has('new')) {
                    window.openTxModal();
                }

                // After a successful create, offer "add another" or "back to list".
                if (new URLSearchParams(window.location.search).has('created')) {
                    openModal('saved-modal');

                    const url = new URL(window.location.href);
                    url.searchParams.delete('created');
                    window.history.replaceState({}, '', url);
                }
            })();

            // AJAX filtering: one box searches every column, no page reload.
            (function () {
                const form = document.getElementById('tx-filters');
                const rows = document.getElementById('tx-rows');
                const pager = document.getElementById('tx-pagination');
                const totalEl = document.getElementById('tx-total');
                const countEl = document.getElementById('tx-count');
                const baseUrl = @json(route($type . '.index'));

                let debounce = null;
                let inFlight = null;
                const sortState = { sort: @json($sort), dir: @json($dir) };

                function paintSort() {
                    document.querySelectorAll('[data-sort-indicator]').forEach((el) => {
                        el.textContent = el.dataset.sortIndicator === sortState.sort
                            ? (sortState.dir === 'asc' ? '▲' : '▼')
                            : '';
                    });
                }

                function buildUrl(page) {
                    const params = new URLSearchParams();

                    new FormData(form).forEach((value, key) => {
                        if (value !== '') params.set(key, value);
                    });

                    params.set('sort', sortState.sort);
                    params.set('dir', sortState.dir);
                    params.set('ajax', '1');
                    if (page) params.set('page', page);

                    return baseUrl + '?' + params.toString();
                }

                async function load(page) {
                    if (inFlight) inFlight.abort();
                    inFlight = new AbortController();

                    rows.classList.add('opacity-40');

                    try {
                        const response = await fetch(buildUrl(page), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            signal: inFlight.signal,
                        });

                        if (!response.ok) throw new Error('HTTP ' + response.status);

                        const data = await response.json();

                        rows.innerHTML = data.rows;
                        pager.innerHTML = data.pagination;
                        pager.classList.toggle('hidden', !data.pagination);
                        totalEl.textContent = data.total;
                        countEl.textContent = '(' + data.count + ')';

                        if (data.sort && data.dir) {
                            sortState.sort = data.sort;
                            sortState.dir = data.dir;
                            paintSort();
                        }
                    } catch (error) {
                        if (error.name !== 'AbortError') console.error(error);
                    } finally {
                        rows.classList.remove('opacity-40');
                    }
                }

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    load();
                });

                form.querySelector('[name="q"]').addEventListener('input', () => {
                    clearTimeout(debounce);
                    debounce = setTimeout(() => load(), 250);
                });

                form.querySelector('[name="category"]').addEventListener('change', () => load());

                // Column headers sort without a reload; direction flips on a repeat click.
                document.querySelector('thead').addEventListener('click', (event) => {
                    const link = event.target.closest('[data-sort-link]');
                    if (!link) return;

                    event.preventDefault();

                    const target = new URL(link.href);
                    sortState.sort = target.searchParams.get('sort');
                    sortState.dir = target.searchParams.get('dir');
                    load();
                });

                // Pagination links are swapped in by AJAX, so listen on the container.
                pager.addEventListener('click', (event) => {
                    const link = event.target.closest('a');
                    if (!link) return;

                    event.preventDefault();
                    load(new URL(link.href).searchParams.get('page'));
                });
            })();
        </script>
    @endpush
</x-app-layout>
