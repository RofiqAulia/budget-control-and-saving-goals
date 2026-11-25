<x-app-layout title="Dashboard" :subtitle="\Illuminate\Support\Carbon::parse($period . '-01')->format('M Y')">
    <x-slot:actions>
        <x-period-picker :period="$period" route="dashboard" />
        <a href="{{ route('expense.index', ['new' => 1]) }}" class="btn btn-primary">
            <x-icon name="plus" class="h-3 w-3" /> Add expense
        </a>
    </x-slot:actions>

    <div class="flex flex-1 flex-col gap-3">

        {{-- Stat strip --}}
        <div class="panel">
            <div class="grid grid-cols-2 divide-line lg:grid-cols-4 lg:divide-x">
                <div class="border-b border-line p-3 lg:border-b-0">
                    <p class="lbl">Income</p>
                    <p class="num mt-1 text-[17px] font-medium text-pos">@money($summary['income'])</p>
                    <p class="mt-0.5 text-[10px] text-faint">Savings rate {{ $summary['savings_rate'] }}%</p>
                </div>
                <div class="border-b border-line p-3 lg:border-b-0">
                    <p class="lbl">Expense</p>
                    <p class="num mt-1 text-[17px] font-medium text-neg">@money($summary['expense'])</p>
                    <p class="mt-0.5 text-[10px] text-faint">
                        {{ $budgets['total_budget'] > 0 ? number_format(($budgets['total_spent'] / $budgets['total_budget']) * 100, 0) : 0 }}% of budget
                    </p>
                </div>
                <div class="border-b border-line p-3 lg:border-b-0">
                    <div class="flex items-baseline justify-between">
                        <p class="lbl">Net cashflow</p>
                        @if (! is_null($growth))
                            <span class="num text-[10px] {{ $growth >= 0 ? 'text-pos' : 'text-neg' }}">
                                {{ $growth >= 0 ? '+' : '' }}{{ $growth }}%
                            </span>
                        @endif
                    </div>
                    <p class="num mt-1 text-[17px] font-medium {{ $summary['net'] >= 0 ? 'text-ink' : 'text-neg' }}">@money($summary['net'])</p>
                    <p class="mt-0.5 text-[10px] text-faint">vs. previous month</p>
                </div>
                <div class="p-3">
                    <p class="lbl">Portfolio</p>
                    <p class="num mt-1 text-[17px] font-medium">@money($portfolio['value'])</p>
                    <p class="num mt-0.5 text-[10px] {{ $portfolio['gain'] >= 0 ? 'text-pos' : 'text-neg' }}">
                        {{ $portfolio['gain'] >= 0 ? '+' : '' }}@money($portfolio['gain']) ({{ $portfolio['gain_percent'] }}%)
                    </p>
                </div>
            </div>
        </div>

        {{-- Charts: the row grows to fill whatever height is left --}}
        <div class="grid min-h-[290px] flex-1 gap-3 lg:grid-cols-3">
            <div class="panel flex flex-col lg:col-span-2">
                <div class="panel-head">
                    <div class="flex items-baseline gap-2">
                        <h2 class="text-[12px] font-medium">Cashflow growth</h2>
                        <span class="text-[10px] text-faint">last 6 months</span>
                    </div>
                    <div class="flex items-center gap-2.5 text-[10px] text-muted">
                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 bg-pos"></span>Income</span>
                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 bg-neg"></span>Expense</span>
                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 bg-accent"></span>Net</span>
                    </div>
                </div>
                <div class="relative flex-1">
                    <div class="absolute inset-3">
                        <canvas id="cashflowChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="panel flex flex-col">
                <div class="panel-head">
                    <h2 class="text-[12px] font-medium">Expense by group</h2>
                    <span class="num text-[10px] text-faint">@money($summary['expense'])</span>
                </div>

                <div class="p-3">
                    @php $maxGroup = $expenseGroups->max('total') ?: 1; @endphp

                    @forelse ($expenseGroups as $group)
                        <div class="mb-2.5 last:mb-0">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate text-[11px] text-ink">{{ $group['group']->name }}</span>
                                <span class="num shrink-0 text-[11px]">@money($group['total'])</span>
                            </div>
                            <div class="bar mt-1">
                                <span style="width: {{ max(2, round(($group['total'] / $maxGroup) * 100)) }}%; background: {{ $group['group']->color ?? '#185fa5' }}"></span>
                            </div>
                            <p class="num mt-0.5 text-[10px] text-faint">
                                {{ $summary['expense'] > 0 ? number_format(($group['total'] / $summary['expense']) * 100, 1) : 0 }}% of expense
                            </p>
                        </div>
                    @empty
                        <p class="py-6 text-center text-[11px] text-faint">No expenses this month.</p>
                    @endforelse
                </div>

                {{-- Pinned to the bottom so the panel never ends in dead space --}}
                <div class="mt-auto flex items-baseline justify-between border-t border-line bg-canvas px-3 py-[5px]">
                    <span class="lbl">Total</span>
                    <span class="num text-[11px] font-medium text-neg">@money($summary['expense'])</span>
                </div>
            </div>
        </div>

        {{-- Savings + budget: fixed row counts, so they pair cleanly --}}
        <div class="grid items-start gap-3 lg:grid-cols-2">
            <div class="panel">
                <div class="panel-head">
                    <h2 class="text-[12px] font-medium">Savings</h2>
                    <a href="{{ route('savings.index') }}" class="text-[10px] text-accent hover:underline">Manage</a>
                </div>
                <div class="p-3">
                    <div class="flex items-baseline justify-between">
                        <p class="num text-[15px] font-medium">@money($savings['saved'])</p>
                        <p class="num text-[10px] text-faint">of @money($savings['target'])</p>
                    </div>
                    <div class="bar mt-1.5"><span style="width: {{ min(100, $savings['percent']) }}%"></span></div>
                    <p class="mt-1 text-[10px] text-faint">{{ $savings['percent'] }}% funded · {{ $savings['count'] }} goals</p>

                    <div class="mt-3 space-y-2 border-t border-line pt-2.5">
                        @foreach (\App\Models\SavingsGoal::forUser(auth()->id())->where('is_completed', false)->orderByDesc('saved_amount')->limit(3)->get() as $goal)
                            <div>
                                <div class="flex items-baseline justify-between text-[11px]">
                                    <span class="truncate text-muted">{{ $goal->name }}</span>
                                    <span class="num text-ink">{{ $goal->progress_percent }}%</span>
                                </div>
                                <div class="bar mt-1"><span style="width: {{ $goal->progress_percent }}%"></span></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2 class="text-[12px] font-medium">Budget</h2>
                    <a href="{{ route('budgets.index') }}" class="text-[10px] text-accent hover:underline">Manage</a>
                </div>
                <div class="p-3">
                    @php $used = $budgets['total_budget'] > 0 ? round(($budgets['total_spent'] / $budgets['total_budget']) * 100) : 0; @endphp

                    <div class="flex items-baseline justify-between">
                        <p class="num text-[15px] font-medium">@money($budgets['total_spent'])</p>
                        <p class="num text-[10px] text-faint">of @money($budgets['total_budget'])</p>
                    </div>
                    <div class="bar mt-1.5"><span style="width: {{ min(100, $used) }}%; {{ $used > 100 ? 'background:#a32d2d' : '' }}"></span></div>
                    <p class="mt-1 text-[10px] {{ $used > 100 ? 'text-neg' : 'text-faint' }}">{{ $used }}% used</p>

                    <div class="mt-3 space-y-2 border-t border-line pt-2.5">
                        @forelse (array_slice($budgets['rows'], 0, 3) as $row)
                            <div>
                                <div class="flex items-baseline justify-between text-[11px]">
                                    <span class="truncate text-muted">{{ $row['category']?->name ?? 'Overall' }}</span>
                                    <span class="num {{ $row['over'] ? 'text-neg' : 'text-ink' }}">{{ $row['percent'] }}%</span>
                                </div>
                                <div class="bar mt-1"><span style="width: {{ min(100, $row['percent']) }}%; {{ $row['over'] ? 'background:#a32d2d' : '' }}"></span></div>
                            </div>
                        @empty
                            <p class="text-[10px] text-faint">No budgets set.</p>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>

        {{-- Recent activity: row count varies, so it sits in its own full-width band --}}
        <div class="panel">
            <div class="panel-head">
                <h2 class="text-[12px] font-medium">Recent activity</h2>
                <a href="{{ route('reports.index') }}" class="text-[10px] text-accent hover:underline">Report</a>
            </div>
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="th w-20">Date</th>
                        <th class="th">Category</th>
                        <th class="th hidden sm:table-cell">Group</th>
                        <th class="th hidden md:table-cell">Note</th>
                        <th class="th hidden md:table-cell w-20">Source</th>
                        <th class="th text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recent as $tx)
                        <tr class="row-hover">
                            <td class="td num whitespace-nowrap text-[11px] text-muted">{{ $tx->date->format('d/m/Y') }}</td>
                            <td class="td font-medium">{{ $tx->category?->name ?? 'Uncategorised' }}</td>
                            <td class="td hidden text-[11px] text-muted sm:table-cell">{{ $tx->category?->parent?->name ?? '—' }}</td>
                            <td class="td hidden max-w-[280px] truncate text-[11px] text-muted md:table-cell">{{ $tx->description ?: '—' }}</td>
                            <td class="td hidden md:table-cell"><span class="chip">{{ $tx->source }}</span></td>
                            <td class="td num text-right font-medium {{ $tx->type === 'income' ? 'text-pos' : 'text-ink' }}">
                                {{ $tx->type === 'income' ? '+' : '−' }}@money($tx->amount)
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-[11px] text-faint">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Six-month ledger: fills the lower viewport with data worth reading --}}
        <div class="panel">
            <div class="panel-head">
                <h2 class="text-[12px] font-medium">Monthly ledger</h2>
                <span class="text-[10px] text-faint">income vs expense vs net</span>
            </div>

            @php
                $ledger = collect($series);
                $avgIncome = $ledger->avg('income') ?: 0;
                $avgExpense = $ledger->avg('expense') ?: 0;
                $avgNet = $ledger->avg('net') ?: 0;
                $avgRate = $avgIncome > 0 ? round(($avgNet / $avgIncome) * 100, 1) : 0;
                $lastPeriod = $ledger->last()['period'] ?? null;
                $maxNet = $ledger->max('net') ?: 1;
            @endphp

            <table class="w-full">
                <thead>
                    <tr>
                        <th class="th">Month</th>
                        <th class="th text-right">Income</th>
                        <th class="th text-right">Expense</th>
                        <th class="th text-right">Net</th>
                        <th class="th text-right">Savings rate</th>
                        <th class="th w-28">Net trend</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ledger as $row)
                        @php $share = $maxNet > 0 ? max(3, round(($row['net'] / $maxNet) * 100)) : 3; @endphp
                        <tr class="row-hover {{ $row['period'] === $lastPeriod ? 'bg-canvas' : '' }}">
                            <td class="td num text-[11px] {{ $row['period'] === $lastPeriod ? 'font-medium' : 'text-muted' }}">
                                {{ \Illuminate\Support\Carbon::parse($row['period'].'-01')->format('M Y') }}
                            </td>
                            <td class="td num text-right text-pos">@money($row['income'], false)</td>
                            <td class="td num text-right text-neg">@money($row['expense'], false)</td>
                            <td class="td num text-right font-medium {{ $row['net'] >= 0 ? 'text-ink' : 'text-neg' }}">@money($row['net'], false)</td>
                            <td class="td num text-right text-muted">
                                {{ $row['income'] > 0 ? number_format(($row['net'] / $row['income']) * 100, 1) : '0.0' }}%
                            </td>
                            <td class="td">
                                <div class="bar"><span style="width: {{ min(100, $share) }}%; {{ $row['net'] < 0 ? 'background:#a32d2d' : '' }}"></span></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-line-strong bg-canvas">
                        <td class="td border-0 font-medium">6-month average</td>
                        <td class="td num border-0 text-right font-medium">@money($avgIncome, false)</td>
                        <td class="td num border-0 text-right font-medium">@money($avgExpense, false)</td>
                        <td class="td num border-0 text-right font-medium">@money($avgNet, false)</td>
                        <td class="td num border-0 text-right font-medium">{{ $avgRate }}%</td>
                        <td class="td border-0"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const series = @json($series);
                const rupiah = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

                new Chart(document.getElementById('cashflowChart'), {
                    type: 'bar',
                    data: {
                        labels: series.map(s => s.label),
                        datasets: [
                            {
                                label: 'Income',
                                data: series.map(s => s.income),
                                backgroundColor: '#0f6e56',
                                borderRadius: 0,
                                barPercentage: 0.72,
                                categoryPercentage: 0.8,
                            },
                            {
                                label: 'Expense',
                                data: series.map(s => s.expense),
                                backgroundColor: '#a32d2d',
                                borderRadius: 0,
                                barPercentage: 0.72,
                                categoryPercentage: 0.8,
                            },
                            {
                                type: 'line',
                                label: 'Net',
                                data: series.map(s => s.net),
                                borderColor: '#185fa5',
                                backgroundColor: '#185fa5',
                                borderWidth: 1.5,
                                pointRadius: 2.5,
                                pointBackgroundColor: '#fff',
                                pointBorderWidth: 1.5,
                                tension: 0.2,
                            },
                        ],
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
                                    label: ctx => `${ctx.dataset.label}: Rp${rupiah.format(ctx.parsed.y)}`,
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: '#8b9199', font: { size: 10, family: 'JetBrains Mono' } },
                            },
                            y: {
                                border: { display: false },
                                grid: { color: '#edf0f3' },
                                ticks: {
                                    color: '#8b9199',
                                    font: { size: 10, family: 'JetBrains Mono' },
                                    callback: value => value >= 1000000 ? (value / 1000000) + 'M' : (value >= 1000 ? (value / 1000) + 'K' : value),
                                },
                            },
                        },
                    },
                });
            });
        </script>
    @endpush
</x-app-layout>
