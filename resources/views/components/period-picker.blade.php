@props(['period', 'route', 'allowAll' => false])

@php
    $months = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
    if (! $months->contains(fn ($m) => $m->format('Y-m') === $period)) {
        $months->push(\Illuminate\Support\Carbon::parse($period.'-01'));
    }
@endphp

<form method="GET" action="{{ route($route) }}" class="flex items-center">
    <select name="period" onchange="this.form.submit()"
            class="field num w-auto cursor-pointer py-[4px] pr-6 text-[11px]">
        @if ($allowAll)
            <option value="" @selected(! request()->filled('period'))>All months</option>
        @endif
        @foreach ($months as $month)
            <option value="{{ $month->format('Y-m') }}" @selected(request()->filled('period') && $month->format('Y-m') === $period)>
                {{ $month->format('M Y') }}
            </option>
        @endforeach
    </select>
</form>
