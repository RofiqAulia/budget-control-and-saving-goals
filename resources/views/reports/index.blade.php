<x-app-layout title="Detail & Reporting" :subtitle="\Illuminate\Support\Carbon::parse($period.'-01')->format('M Y')">
    <x-slot:actions>
        <x-period-picker :period="$period" route="reports.index" />
        <a href="{{ route('reports.export', ['period' => $period]) }}" class="btn">
            <x-icon name="download" class="h-3 w-3" /> CSV
        </a>
        <form method="POST" action="{{ route('reports.sync') }}">
            @csrf
            <input type="hidden" name="period" value="{{ $period }}">
            <button class="btn btn-primary">
                <x-icon name="sheets" class="h-3 w-3" /> Sync all to Sheets
            </button>
        </form>
    </x-slot:actions>

    <div class="panel">
        <div class="grid grid-cols-3 divide-x divide-line">
            <div class="p-3">
                <p class="lbl">Total income</p>
                <p class="num mt-1 text-[17px] font-medium text-pos">@money($incomeTotal)</p>
            </div>
            <div class="p-3">
                <p class="lbl">Total expense</p>
                <p class="num mt-1 text-[17px] font-medium text-neg">@money($expenseTotal)</p>
            </div>
            <div class="p-3">
                <p class="lbl">Net</p>
                <p class="num mt-1 text-[17px] font-medium {{ $summary['net'] >= 0 ? 'text-ink' : 'text-neg' }}">@money($summary['net'])</p>
            </div>
        </div>
    </div>

    <div class="mt-3 grid items-start gap-3 lg:grid-cols-4">
        <div class="panel lg:col-span-3">
            <div class="panel-head">
                <h2 class="text-[12px] font-medium">Monthly report</h2>
                <div class="flex items-center gap-2 text-[10px] text-faint">
                    <span class="num">{{ \Illuminate\Support\Carbon::parse($period.'-01')->format('M Y') }}</span>
                    <span class="text-line-strong">|</span>
                    <span>IDR</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="th w-20">Type</th>
                            <th class="th w-40">Group</th>
                            <th class="th">Category</th>
                            <th class="th text-right">Amount</th>
                            <th class="th text-right">Group total</th>
                        </tr>
                    </thead>

                    @foreach ([['label' => 'Income', 'data' => $income, 'total' => $incomeTotal, 'tone' => 'pos'], ['label' => 'Expense', 'data' => $expense, 'total' => $expenseTotal, 'tone' => 'neg']] as $section)
                        <tbody>
                            <tr class="border-y border-line bg-canvas">
                                <td colspan="5" class="px-2.5 py-[3px]">
                                    <span class="lbl">{{ $section['label'] }}</span>
                                </td>
                            </tr>

                            @foreach ($section['data'] as $group)
                                @php $children = $group['children']; @endphp

                                @if (empty($children))
                                    <tr class="row-hover">
                                        <td class="td text-[10px] text-faint">{{ $loop->first ? $section['label'] : '' }}</td>
                                        <td class="td">{{ $group['group']->name }}</td>
                                        <td class="td text-faint">—</td>
                                        <td class="td num text-right text-faint">—</td>
                                        <td class="td num text-right text-muted">{{ $group['total'] > 0 ? \App\Support\Money::format($group['total'], false) : '—' }}</td>
                                    </tr>
                                @else
                                    @foreach ($children as $child)
                                        <tr class="row-hover">
                                            <td class="td text-[10px] text-faint">{{ $loop->parent->first && $loop->first ? $section['label'] : '' }}</td>
                                            <td class="td text-muted">{{ $loop->first ? $group['group']->name : '' }}</td>
                                            <td class="td">{{ $child['name'] }}</td>
                                            <td class="td num text-right {{ $child['amount'] > 0 ? '' : 'text-faint' }}">
                                                {{ $child['amount'] > 0 ? \App\Support\Money::format($child['amount'], false) : '—' }}
                                            </td>
                                            <td class="td num text-right font-medium">
                                                {{ $loop->first ? \App\Support\Money::format($group['total'], false) : '' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach

                            <tr class="border-y border-line-strong bg-canvas">
                                <td colspan="3" class="px-2.5 py-[4px] text-right">
                                    <span class="lbl">{{ $section['label'] }} total</span>
                                </td>
                                <td colspan="2" class="num px-2.5 py-[4px] text-right text-[12px] font-medium {{ $section['tone'] === 'pos' ? 'text-pos' : 'text-neg' }}">
                                    @money($section['total'])
                                </td>
                            </tr>
                        </tbody>
                    @endforeach

                    <tfoot>
                        <tr class="bg-white">
                            <td colspan="3" class="px-2.5 py-[6px] text-right">
                                <span class="lbl">Net</span>
                            </td>
                            <td colspan="2" class="num px-2.5 py-[6px] text-right text-[14px] font-medium {{ $summary['net'] >= 0 ? 'text-ink' : 'text-neg' }}">
                                @money($summary['net'])
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="space-y-3">
            <div class="panel">
                <div class="panel-head">
                    <h2 class="text-[12px] font-medium">Google Sheets</h2>
                    <span class="chip {{ $sheets->is_active && $sheets->configValue('spreadsheet_id') ? 'border-pos/40 text-pos' : '' }}">
                        {{ $sheets->is_active && $sheets->configValue('spreadsheet_id') ? 'connected' : 'off' }}
                    </span>
                </div>
                <div class="space-y-2 p-3">
                    @if ($sheets->last_synced_at)
                        <p class="text-[10px] text-faint">Last sync {{ $sheets->last_synced_at->diffForHumans() }}</p>
                    @endif

                    @if ($sheets->last_error)
                        <p class="border border-neg/40 bg-neg-soft px-2 py-1.5 text-[10px] leading-relaxed text-neg">{{ $sheets->last_error }}</p>
                    @endif

                    <a href="{{ route('settings.index') }}" class="btn w-full justify-center">
                        <x-icon name="settings" class="h-3 w-3" /> Configure
                    </a>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2 class="text-[12px] font-medium">Export notes</h2>
                </div>
                <ul class="space-y-1 p-3 text-[10px] leading-relaxed text-muted">
                    <li>Sync writes <span class="text-ink">every transaction</span> plus a monthly summary — not just the month above.</li>
                    <li>It replaces the whole tab, so use a dedicated sheet.</li>
                    <li>CSV still exports only the selected month.</li>
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
