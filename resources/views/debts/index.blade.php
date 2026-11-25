<x-app-layout title="Debts" subtitle="What you owe and what is owed to you">
    <x-slot:actions>
        <button type="button" onclick="openDebtModal()" class="btn btn-primary">
            <x-icon name="plus" class="h-3 w-3" /> Add debt
        </button>
    </x-slot:actions>

    <div class="panel">
        <div class="grid grid-cols-2 divide-x divide-line lg:grid-cols-4">
            <div class="border-b border-line p-3 lg:border-b-0">
                <p class="lbl">You owe</p>
                <p class="num mt-1 text-[17px] font-medium {{ $summary['payable'] > 0 ? 'text-neg' : '' }}">@money($summary['payable'])</p>
            </div>
            <div class="border-b border-line p-3 lg:border-b-0">
                <p class="lbl">Owed to you</p>
                <p class="num mt-1 text-[17px] font-medium {{ $summary['receivable'] > 0 ? 'text-pos' : 'text-muted' }}">@money($summary['receivable'])</p>
            </div>
            <div class="border-b border-line p-3 lg:border-b-0">
                <p class="lbl">Minimum / month</p>
                <p class="num mt-1 text-[17px] font-medium">@money($summary['monthly'])</p>
            </div>
            <div class="p-3">
                <p class="lbl">Settled</p>
                <p class="num mt-1 text-[17px] font-medium">{{ $summary['settled'] }}</p>
            </div>
        </div>
    </div>

    <div class="mt-3 grid gap-3 lg:grid-cols-3">
        <div class="panel lg:col-span-2">
            <div class="panel-head">
                <h2 class="text-[12px] font-medium">Payoff projection</h2>
                <span class="num text-[10px] text-faint">
                    @if ($projection['monthly'] > 0)
                        @money($projection['monthly']) / month
                    @else
                        no minimum set
                    @endif
                </span>
            </div>

            <div class="h-44 p-3">
                <canvas id="payoffChart"></canvas>
            </div>

            <p class="border-t border-line bg-canvas px-3 py-[5px] text-[10px] leading-relaxed text-faint">
                @if ($projection['months_to_zero'])
                    At the current minimum payment, everything is cleared in {{ $projection['months_to_zero'] }} months.
                @elseif ($projection['monthly'] <= 0)
                    Set a minimum payment on each debt to see a payoff curve.
                @else
                    Not settled within 12 months at the current minimum payment.
                @endif
            </p>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2 class="text-[12px] font-medium">Composition</h2>
                <span class="num text-[10px] text-faint">{{ $composition->count() }}</span>
            </div>

            @if ($composition->isEmpty())
                <p class="p-3 text-[11px] text-faint">No active debts.</p>
            @else
                <div class="divide-y divide-[#edf0f3]">
                    @foreach ($composition as $row)
                        @php $share = $summary['payable'] > 0 ? (int) round($row['total'] / $summary['payable'] * 100) : 0; @endphp
                        <div class="px-3 py-[7px]">
                            <div class="flex items-baseline justify-between gap-2 text-[11px]">
                                <span class="truncate">{{ $row['name'] }}</span>
                                <span class="num shrink-0 text-faint">{{ $share }}%</span>
                            </div>
                            <div class="mt-1 flex items-center gap-2">
                                <div class="bar flex-1"><span style="width: {{ max(2, $share) }}%; background: {{ $row['color'] }}"></span></div>
                                <span class="num shrink-0 text-[10px] text-muted">@money($row['total'])</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="mt-3 panel">
        <div class="panel-head">
            <h2 class="text-[12px] font-medium">Debts &amp; receivables</h2>
            <span class="num text-[10px] text-faint">{{ $debts->count() }} total</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="th">Name</th>
                        <th class="th w-24">Type</th>
                        <th class="th w-32 text-right">Remaining</th>
                        <th class="th w-32">Progress</th>
                        <th class="th w-28 text-right">Min / month</th>
                        <th class="th w-28">Due</th>
                        <th class="th w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($debts as $debt)
                        @php $overdue = ! $debt->is_settled && $debt->due_date && $debt->due_date->isPast(); @endphp
                        <tr class="row-hover {{ $debt->is_settled ? 'opacity-50' : '' }}">
                            <td class="td">
                                <p class="font-medium">{{ $debt->name }}</p>
                                @if ($debt->note)
                                    <p class="max-w-[240px] truncate text-[10px] text-faint">{{ $debt->note }}</p>
                                @endif
                            </td>
                            <td class="td">
                                <span class="chip">{{ $debt->is_receivable ? 'Receivable' : 'Debt' }}</span>
                            </td>
                            <td class="td num text-right font-medium {{ $debt->is_receivable ? 'text-pos' : '' }}">
                                @money($debt->remaining_amount)
                            </td>
                            <td class="td">
                                <div class="flex items-center gap-1.5">
                                    <div class="bar flex-1">
                                        <span style="width: {{ $debt->progress_percent }}%; {{ $debt->is_settled ? 'background:#3f7d4e' : '' }}"></span>
                                    </div>
                                    <span class="num text-[10px] text-faint">{{ $debt->progress_percent }}%</span>
                                </div>
                            </td>
                            <td class="td num text-right text-[11px] text-muted">
                                @if ($debt->minimum_payment)
                                    @money($debt->minimum_payment)
                                @else
                                    —
                                @endif
                            </td>
                            <td class="td num text-[11px] {{ $overdue ? 'text-neg' : 'text-muted' }}">
                                {{ $debt->due_date ? $debt->due_date->format('d/m/Y') : '—' }}
                            </td>
                            <td class="td">
                                <div class="flex items-center justify-end gap-0.5">
                                    @if (! $debt->is_settled)
                                        <button type="button" onclick="openPayModal(this)"
                                                data-action="{{ route('debts.pay', $debt) }}"
                                                data-name="{{ $debt->name }}"
                                                data-remaining="{{ number_format($debt->remaining_amount, 0, '', '') }}"
                                                class="p-1 text-faint transition hover:text-pos" title="Record payment">
                                            <x-icon name="check" class="h-3.5 w-3.5" />
                                        </button>
                                    @endif
                                    <button type="button" onclick="openDebtModal(this)"
                                            data-id="{{ $debt->id }}"
                                            data-action="{{ route('debts.update', $debt) }}"
                                            data-name="{{ $debt->name }}"
                                            data-category="{{ $debt->category_id }}"
                                            data-type="{{ $debt->type }}"
                                            data-principal="{{ number_format($debt->principal_amount, 0, '', '') }}"
                                            data-remaining="{{ number_format($debt->remaining_amount, 0, '', '') }}"
                                            data-interest="{{ $debt->interest_rate }}"
                                            data-minimum="{{ $debt->minimum_payment ? number_format($debt->minimum_payment, 0, '', '') : '' }}"
                                            data-due="{{ $debt->due_date?->toDateString() }}"
                                            data-note="{{ $debt->note }}"
                                            class="p-1 text-faint transition hover:text-accent" title="Edit">
                                        <x-icon name="edit" class="h-3.5 w-3.5" />
                                    </button>
                                    <form method="POST" action="{{ route('debts.destroy', $debt) }}"
                                          onsubmit="event.preventDefault(); askConfirm(this, 'Delete &quot;{{ $debt->name }}&quot; and its payment history?', { danger: true, label: 'Delete' })">
                                        @csrf
                                        @method('DELETE')
                                        <button class="p-1 text-faint transition hover:text-neg" title="Delete">
                                            <x-icon name="trash" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center">
                                <p class="text-[12px] font-medium">No debts recorded</p>
                                <p class="mt-0.5 text-[11px] text-faint">Add a loan, instalment, or money someone owes you.</p>
                                <button type="button" onclick="openDebtModal()" class="btn btn-primary mt-2">
                                    <x-icon name="plus" class="h-3 w-3" /> Add debt
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-2 px-1 text-[10px] leading-relaxed text-faint">
        Outstanding debts are subtracted from your portfolio to give net worth, and shown alongside savings so you can see the real position.
    </p>

    <x-modal id="debt-modal" title="Add debt">
        <form id="debt-form" method="POST" action="{{ route('debts.store') }}">
            @csrf
            <input type="hidden" name="_method" value="POST" id="debt-method">

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="debt-category" class="lbl mb-1 block">Category</label>
                    <select name="category_id" id="debt-category" class="field" required>
                        <option value="">Choose a debt category…</option>
                        @foreach ($debtCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                        <option value="__new">＋ Add debt category…</option>
                    </select>

                    @if ($debtCategories->isEmpty())
                        <p class="mt-1 text-[10px] text-faint">
                            Belum ada kategori Debts. Pilih "Add debt category…" untuk membuatnya lebih dulu.
                        </p>
                    @endif
                </div>

                <div>
                    <label class="lbl mb-1 block">Type</label>
                    <select name="type" class="field">
                        <option value="debt">Debt — I owe this</option>
                        <option value="receivable">Receivable — owed to me</option>
                    </select>
                </div>

                <div>
                    <label class="lbl mb-1 block">Original amount</label>
                    <input name="principal_amount" required inputmode="numeric" placeholder="12.000.000" class="field num">
                </div>

                <div>
                    <label class="lbl mb-1 block">Remaining</label>
                    <input name="remaining_amount" inputmode="numeric" placeholder="same as original" class="field num">
                </div>

                <div>
                    <label class="lbl mb-1 block">Interest / year <span class="normal-case tracking-normal">(optional)</span></label>
                    <input name="interest_rate" inputmode="decimal" placeholder="6.5" class="field num">
                </div>

                <div>
                    <label class="lbl mb-1 block">Minimum / month <span class="normal-case tracking-normal">(optional)</span></label>
                    <input name="minimum_payment" inputmode="numeric" placeholder="1.100.000" class="field num">
                </div>

                <div>
                    <label class="lbl mb-1 block">Final due date <span class="normal-case tracking-normal">(optional)</span></label>
                    <input name="due_date" type="date" class="field num">
                </div>

                <div class="sm:col-span-2">
                    <label class="lbl mb-1 block">Note <span class="normal-case tracking-normal">(optional)</span></label>
                    <input name="note" maxlength="255" placeholder="e.g. BCA, 24 months left" class="field">
                </div>
            </div>

            <div class="mt-4 flex items-center gap-1.5 border-t border-line pt-3">
                <button class="btn btn-primary">Save</button>
                <button type="button" onclick="closeModal('debt-modal')" class="btn">Cancel</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="pay-modal" title="Record payment" width="max-w-sm">
        <form id="pay-form" method="POST" action="">
            @csrf

            <p id="pay-target" class="mb-3 text-[11px] text-muted"></p>

            <div class="space-y-3">
                <div>
                    <label class="lbl mb-1 block">Amount</label>
                    <input name="amount" required inputmode="numeric" class="field num">
                </div>
                <div>
                    <label class="lbl mb-1 block">Date</label>
                    <input name="paid_on" type="date" value="{{ now()->toDateString() }}" class="field num">
                </div>
                <div>
                    <label class="lbl mb-1 block">Note <span class="normal-case tracking-normal">(optional)</span></label>
                    <input name="note" maxlength="255" class="field">
                </div>
            </div>

            <div class="mt-4 flex items-center gap-1.5 border-t border-line pt-3">
                <button class="btn btn-primary">Record payment</button>
                <button type="button" onclick="closeModal('pay-modal')" class="btn">Cancel</button>
            </div>
        </form>
    </x-modal>

    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('debt-form');
                const method = document.getElementById('debt-method');
                const title = document.querySelector('#debt-modal [data-modal-title]');
                const submit = form.querySelector('.btn-primary');
                const storeAction = @json(route('debts.store'));
                const categoriesUrl = @json(route('debts.categories'));
                const categorySelect = document.getElementById('debt-category');

                // The last option leaves the form and opens the category manager.
                categorySelect.addEventListener('change', function () {
                    if (this.value === '__new') {
                        window.location.href = categoriesUrl;
                    }
                });

                window.openDebtModal = function (button) {
                    form.reset();
                    const editing = button && button.dataset.id;

                    if (editing) {
                        form.action = button.dataset.action;
                        method.value = 'PUT';
                        categorySelect.value = button.dataset.category || '';
                        form.querySelector('[name="type"]').value = button.dataset.type;
                        form.querySelector('[name="principal_amount"]').value = button.dataset.principal;
                        form.querySelector('[name="remaining_amount"]').value = button.dataset.remaining;
                        form.querySelector('[name="interest_rate"]').value = button.dataset.interest || '';
                        form.querySelector('[name="minimum_payment"]').value = button.dataset.minimum || '';
                        form.querySelector('[name="due_date"]').value = button.dataset.due || '';
                        form.querySelector('[name="note"]').value = button.dataset.note || '';
                        title.textContent = 'Edit debt';
                        submit.textContent = 'Update';
                    } else {
                        form.action = storeAction;
                        method.value = 'POST';
                        title.textContent = 'Add debt';
                        submit.textContent = 'Save';
                    }

                    openModal('debt-modal');
                };

                const payForm = document.getElementById('pay-form');
                const payTarget = document.getElementById('pay-target');

                window.openPayModal = function (button) {
                    payForm.reset();
                    payForm.action = button.dataset.action;
                    payForm.querySelector('[name="amount"]').value = button.dataset.remaining;
                    payTarget.textContent = button.dataset.name + ' — remaining ' + Number(button.dataset.remaining).toLocaleString('id-ID');
                    openModal('pay-modal');
                };
            })();

            document.addEventListener('DOMContentLoaded', () => {
                const canvas = document.getElementById('payoffChart');

                if (!canvas) return;

                const projection = @json($projection);
                const rupiah = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: projection.labels,
                        datasets: [{
                            label: 'Outstanding',
                            data: projection.balances,
                            borderColor: '#a32d2d',
                            backgroundColor: 'rgba(163, 45, 45, 0.08)',
                            borderWidth: 1.5,
                            fill: true,
                            pointRadius: 2,
                            pointBackgroundColor: '#fff',
                            pointBorderWidth: 1.5,
                            tension: 0.25,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1b1f24',
                                titleFont: { size: 11 },
                                bodyFont: { size: 11, family: 'JetBrains Mono' },
                                padding: 8,
                                cornerRadius: 2,
                                callbacks: {
                                    label: (ctx) => 'Outstanding: Rp' + rupiah.format(ctx.parsed.y),
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 9 }, color: '#8a929c' },
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#edf0f3' },
                                ticks: {
                                    font: { size: 9 },
                                    color: '#8a929c',
                                    callback: (value) => value === 0 ? '0' : (value / 1_000_000).toFixed(0) + 'M',
                                },
                            },
                        },
                    },
                });
            });
        </script>
    @endpush
</x-app-layout>
