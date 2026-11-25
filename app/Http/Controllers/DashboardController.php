<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);
        $report = ReportService::for($request->user()->id);

        $summary = $report->monthlySummary($period);
        $series = $report->growthSeries(6);
        $portfolio = $report->portfolio();
        $savings = $report->savings();
        $budgets = $report->budgetOverview($period);

        // Month-over-month growth of net cashflow.
        $previous = $series[count($series) - 2] ?? null;
        $current = $series[count($series) - 1] ?? null;
        $growth = ($previous && $previous['net'] != 0 && $current)
            ? round((($current['net'] - $previous['net']) / abs($previous['net'])) * 100, 1)
            : null;

        $expenseGroups = $report->hierarchicalReport($period, 'expense')
            ->filter(fn ($group) => $group['total'] > 0)
            ->sortByDesc('total')
            ->take(6)
            ->values();

        $recent = Transaction::forUser($request->user()->id)
            ->with(['category.parent'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'period' => $period,
            'summary' => $summary,
            'series' => $series,
            'growth' => $growth,
            'portfolio' => $portfolio,
            'savings' => $savings,
            'budgets' => $budgets,
            'expenseGroups' => $expenseGroups,
            'recent' => $recent,
        ]);
    }

    private function resolvePeriod(Request $request): string
    {
        $period = (string) $request->query('period', '');

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return Carbon::now()->format('Y-m');
        }

        return $period;
    }
}
