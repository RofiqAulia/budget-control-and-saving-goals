@php $isIncome = $type === 'income'; @endphp

<div class="space-y-3">
    <div class="panel">
        <div class="panel-head">
            <h2 class="text-[12px] font-medium">This month</h2>
            <span class="num text-[10px] text-faint">{{ $context['month_count'] }} entries</span>
        </div>
        <div class="p-3">
            <p class="num text-[17px] font-medium {{ $isIncome ? 'text-pos' : 'text-neg' }}">@money($context['month_total'])</p>
            <p class="text-[10px] text-faint">total {{ $type }} so far</p>

            @if ($context['top']->isNotEmpty())
                <div class="mt-3 space-y-1.5 border-t border-line pt-2.5">
                    <p class="lbl">Top categories</p>
                    @foreach ($context['top'] as $row)
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="truncate text-[11px] text-muted">{{ $row['name'] }}</span>
                            <span class="num shrink-0 text-[11px]">@money($row['total'])</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2 class="text-[12px] font-medium">Recent {{ strtolower(ucfirst($type)) }}</h2>
            <a href="{{ route($type.'.index') }}" class="text-[10px] text-accent hover:underline">All</a>
        </div>

        <table class="w-full">
            <tbody>
                @forelse ($context['recent'] as $row)
                    <tr class="row-hover border-b border-[#edf0f3] last:border-0">
                        <td class="num py-[5px] pl-3 pr-1 text-[10px] text-faint">{{ $row->date->format('d/m') }}</td>
                        <td class="truncate py-[5px] pr-1 text-[11px]">{{ $row->category?->name ?? 'Uncategorised' }}</td>
                        <td class="num py-[5px] pl-1 pr-3 text-right text-[11px] {{ $isIncome ? 'text-pos' : 'text-ink' }}">@money($row->amount)</td>
                    </tr>
                @empty
                    <tr><td class="py-6 text-center text-[11px] text-faint">Nothing logged yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @php
        $trendValues = collect($context['trend']);
        $trendMax = $trendValues->max('value') ?: 1;
    @endphp

    <div class="panel">
        <div class="panel-head">
            <h2 class="text-[12px] font-medium">6-month {{ $type }}</h2>
            <span class="text-[10px] text-faint">monthly total</span>
        </div>
        <div class="p-3">
            <div class="flex gap-1.5">
                @foreach ($context['trend'] as $bar)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <div class="flex h-14 w-full items-end justify-center">
                            <div class="w-2.5 {{ $isIncome ? 'bg-pos' : 'bg-neg' }}"
                                 style="height: {{ max(4, round(($bar['value'] / $trendMax) * 100)) }}%"
                                 title="{{ $bar['label'] }}: {{ \App\Support\Money::format($bar['value']) }}"></div>
                        </div>
                        <span class="num text-[10px] text-faint">{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 flex items-baseline justify-between border-t border-line pt-1.5">
                <span class="text-[10px] text-faint">6-month total</span>
                <span class="num text-[11px]">@money($trendValues->sum('value'))</span>
            </div>
        </div>
    </div>
</div>
