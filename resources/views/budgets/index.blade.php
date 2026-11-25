<x-app-layout title="Budgeting" :subtitle="\Illuminate\Support\Carbon::parse($period.'-01')->format('M Y') . ' · plan vs actual'">
    <x-slot:actions>
        <x-period-picker :period="$period" route="budgets.index" />
    </x-slot:actions>

    @php
        $totalBudget = $overview['total_budget'];
        $totalSpent = $overview['total_spent'];
        $remaining = $totalBudget - $totalSpent;
        $usedPct = $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100) : 0;

        $periodStart = \Illuminate\Support\Carbon::parse($period.'-01');
        $isCurrentMonth = $periodStart->isSameMonth(now());
        $daysInMonth = $periodStart->daysInMonth;
        $elapsedDays = $isCurrentMonth ? max(1, now()->day) : $daysInMonth;
        $daysLeft = max(1, $daysInMonth - $elapsedDays + 1);
        $safePerDay = $remaining > 0 ? $remaining / $daysLeft : 0;
        $actualPerDay = $totalSpent / $elapsedDays;
    @endphp

    <div class="panel">
        <div class="grid grid-cols-3 divide-x divide-line">
            <div class="p-3">
                <p class="lbl">Planned</p>
                <p class="num mt-1 text-[17px] font-medium">@money($totalBudget)</p>
            </div>
            <div class="p-3">
                <p class="lbl">Spent</p>
                <p class="num mt-1 text-[17px] font-medium text-neg">@money($totalSpent)</p>
            </div>
            <div class="p-3">
                <p class="lbl">{{ $remaining >= 0 ? 'Remaining' : 'Over budget' }}</p>
                <p class="num mt-1 text-[17px] font-medium {{ $remaining >= 0 ? 'text-pos' : 'text-neg' }}">@money(abs($remaining))</p>
            </div>
        </div>

        {{-- Runway folded into the strip so it doesn't need a panel of its own --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line bg-canvas px-3 py-[6px] text-[10px] text-muted">
            <span><span class="num">{{ $daysLeft }}</span> days left</span>
            <span>Safe to spend <span class="num text-accent">@money($safePerDay)</span>/day</span>
            <span>Actual <span class="num {{ $safePerDay > 0 && $actualPerDay > $safePerDay ? 'text-neg' : 'text-ink' }}">@money($actualPerDay)</span>/day</span>
            <span class="text-faint">
                @if ($safePerDay <= 0)
                    Budget used up — every rupiah from here is overspend.
                @elseif ($actualPerDay > $safePerDay)
                    Ahead of plan: trim about @money($actualPerDay - $safePerDay) per day.
                @else
                    On track.
                @endif
            </span>
        </div>
    </div>

    <div class="mt-3 grid items-start gap-3 lg:grid-cols-3">
        <div class="panel lg:col-span-2">
            <div class="panel-head">
                <h2 class="text-[12px] font-medium">Category budgets</h2>
                <div class="flex items-center gap-2">
                    <div class="bar w-24"><span style="width: {{ min(100, $usedPct) }}%; {{ $usedPct > 100 ? 'background:#a32d2d' : '' }}"></span></div>
                    <span class="num text-[10px] {{ $usedPct > 100 ? 'text-neg' : 'text-muted' }}">{{ $usedPct }}%</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="th">Group</th>
                            <th class="th text-right">Spent</th>
                            <th class="th text-right">Limit</th>
                            <th class="th w-44">Usage</th>
                            <th class="th w-32 text-right">Adjust</th>
                            <th class="th w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($overview['rows'] as $row)
                            <tr class="row-hover">
                                <td class="td">
                                    <div class="flex items-center gap-1.5">
                                        <span class="h-2 w-2 shrink-0" style="background: {{ $row['category']?->color ?? '#8b9199' }}"></span>
                                        <span class="font-medium">{{ $row['category']?->name ?? 'Overall' }}</span>
                                        @if ($row['over'])
                                            <span class="chip border-neg/40 text-neg">over</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="td num text-right {{ $row['over'] ? 'text-neg' : '' }}">@money($row['spent'])</td>
                                <td class="td num text-right text-muted">@money($row['amount'])</td>
                                <td class="td">
                                    <div class="flex items-center gap-1.5">
                                        <div class="bar flex-1"><span style="width: {{ min(100, $row['percent']) }}%; {{ $row['over'] ? 'background:#a32d2d' : ($row['percent'] > 80 ? 'background:#854f0b' : '') }}"></span></div>
                                        <span class="num w-9 shrink-0 text-right text-[10px] text-muted">{{ $row['percent'] }}%</span>
                                    </div>
                                </td>
                                <td class="td">
                                    <form method="POST" action="{{ route('budgets.update', $row['budget']) }}" class="flex items-center justify-end gap-1">
                                        @csrf
                                        @method('PUT')
                                        <input name="amount" value="{{ number_format((float) $row['amount'], 0, ',', '.') }}" inputmode="numeric"
                                               class="field num w-24 py-[2px] text-right text-[11px]">
                                        <button class="btn px-1.5 py-[2px]">Save</button>
                                    </form>
                                </td>
                                <td class="td">
                                    <form method="POST" action="{{ route('budgets.destroy', $row['budget']) }}" onsubmit="event.preventDefault(); askConfirm(this, 'Remove this budget?', { danger: true, label: 'Delete' })">
                                        @csrf
                                        @method('DELETE')
                                        <button class="p-1 text-faint transition hover:text-neg" title="Remove">
                                            <x-icon name="trash" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center">
                                    <p class="text-[12px] font-medium">No budgets this month</p>
                                    <p class="mt-0.5 text-[11px] text-faint">Pick a group on the right to start planning.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-3">
            <div class="panel">
                <div class="panel-head">
                    <h2 class="text-[12px] font-medium">Add budget</h2>
                </div>
                <form method="POST" action="{{ route('budgets.store') }}" class="space-y-2.5 p-3">
                    @csrf
                    <input type="hidden" name="period" value="{{ $period }}">

                    <div>
                        <label class="lbl mb-1 block">Category group</label>
                        <select name="category_id" required class="field">
                            <option value="">Select…</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="lbl mb-1 block">Monthly limit</label>
                        <input name="amount" required inputmode="numeric" placeholder="2.200.000" class="field num">
                    </div>

                    <button class="btn btn-primary w-full justify-center">Save budget</button>
                </form>
            </div>

            @if ($unbudgeted->isNotEmpty())
                <div class="panel">
                    <div class="panel-head">
                        <h2 class="text-[12px] font-medium">Not budgeted</h2>
                        <span class="num text-[10px] text-faint">{{ $unbudgeted->count() }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1 p-3">
                        @foreach ($unbudgeted as $group)
                            <form method="POST" action="{{ route('budgets.store') }}">
                                @csrf
                                <input type="hidden" name="period" value="{{ $period }}">
                                <input type="hidden" name="category_id" value="{{ $group->id }}">
                                <input type="hidden" name="amount" value="0">
                                <button class="chip transition hover:border-accent hover:text-accent">+ {{ $group->name }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($topCategories->isNotEmpty())
        @php $topMax = $topCategories->max('total') ?: 1; @endphp

        <div class="panel mt-3">
            <div class="panel-head">
                <h2 class="text-[12px] font-medium">Top spending categories</h2>
                <span class="num text-[10px] text-faint">{{ $topCategories->count() }} categories</span>
            </div>

            <table class="w-full">
                <thead>
                    <tr>
                        <th class="th w-64">Category</th>
                        <th class="th">Share</th>
                        <th class="th w-28 text-right">Spent</th>
                        <th class="th w-20 text-right">Of total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topCategories as $row)
                        <tr class="row-hover">
                            <td class="td">
                                <p class="font-medium">{{ $row['name'] }}</p>
                                <p class="text-[10px] text-faint">{{ $row['group'] }}</p>
                            </td>
                            <td class="td">
                                <div class="bar"><span style="width: {{ max(2, round(($row['total'] / $topMax) * 100)) }}%; background: {{ $row['color'] ?? '#185fa5' }}"></span></div>
                            </td>
                            <td class="td num text-right">@money($row['total'])</td>
                            <td class="td num text-right text-[10px] text-faint">
                                {{ $overview['total_spent'] > 0 ? number_format(($row['total'] / $overview['total_spent']) * 100, 1) : 0 }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
