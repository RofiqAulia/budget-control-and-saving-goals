<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Holding;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private int $userId) {}

    public static function for(int $userId): self
    {
        return new self($userId);
    }

    /**
     * Income, expense and net for a single month.
     *
     * @return array{income: float, expense: float, net: float, savings_rate: float}
     */
    public function monthlySummary(string $period): array
    {
        $rows = Transaction::forUser($this->userId)
            ->inPeriod($period)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $income = (float) ($rows['income'] ?? 0);
        $expense = (float) ($rows['expense'] ?? 0);

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'savings_rate' => $income > 0 ? round((($income - $expense) / $income) * 100, 1) : 0.0,
        ];
    }

    /**
     * Month-by-month income/expense series, oldest first.
     *
     * @return array<int, array{period: string, label: string, income: float, expense: float, net: float}>
     */
    public function growthSeries(int $months = 6): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $rows = Transaction::forUser($this->userId)
            ->where('date', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as period, type, SUM(amount) as total")
            ->groupBy('period', 'type')
            ->get();

        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $period = $month->format('Y-m');

            $income = (float) $rows->where('period', $period)->where('type', 'income')->sum('total');
            $expense = (float) $rows->where('period', $period)->where('type', 'expense')->sum('total');

            $series[] = [
                'period' => $period,
                'label' => $month->format('M'),
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
            ];
        }

        return $series;
    }

    /**
     * The hierarchical report: group -> leaf categories -> subtotal.
     *
     * @return Collection<int, array{group: Category, children: array<int, array{name: string, amount: float}>, total: float}>
     */
    public function hierarchicalReport(string $period, string $type): Collection
    {
        $sums = Transaction::forUser($this->userId)
            ->inPeriod($period)
            ->where('type', $type)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $groups = Category::forUser($this->userId)
            ->ofType($type)
            ->roots()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return $groups->map(function (Category $group) use ($sums) {
            $children = $group->children->map(fn (Category $child) => [
                'name' => $child->name,
                'amount' => (float) ($sums[$child->id] ?? 0),
            ])->all();

            $childTotal = array_sum(array_column($children, 'amount'));

            return [
                'group' => $group,
                'children' => $children,
                // Include anything logged directly against the group itself.
                'total' => $childTotal + (float) ($sums[$group->id] ?? 0),
            ];
        });
    }

    /**
     * Budget vs actual spending for a period.
     *
     * @return array{rows: array<int, array<string, mixed>>, total_budget: float, total_spent: float}
     */
    public function budgetOverview(string $period): array
    {
        $spentByGroup = DB::table('transactions as t')
            ->leftJoin('categories as c', 'c.id', '=', 't.category_id')
            ->where('t.user_id', $this->userId)
            ->where('t.type', 'expense')
            ->where('t.date', '>=', Carbon::parse($period.'-01')->toDateString())
            ->where('t.date', '<=', Carbon::parse($period.'-01')->endOfMonth()->toDateString())
            ->selectRaw('COALESCE(c.parent_id, c.id) as group_id, SUM(t.amount) as total')
            ->groupBy('group_id')
            ->pluck('total', 'group_id');

        $budgets = Budget::forUser($this->userId)
            ->forPeriod($period)
            ->with('category')
            ->get()
            ->sortBy(fn (Budget $b) => $b->category?->sort_order ?? 999);

        $rows = $budgets->map(function (Budget $budget) use ($spentByGroup) {
            $spent = (float) ($spentByGroup[$budget->category_id] ?? 0);
            $amount = (float) $budget->amount;

            return [
                'budget' => $budget,
                'category' => $budget->category,
                'amount' => $amount,
                'spent' => $spent,
                'remaining' => $amount - $spent,
                'percent' => $amount > 0 ? round(($spent / $amount) * 100, 1) : 0.0,
                'over' => $spent > $amount,
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'total_budget' => array_sum(array_column($rows, 'amount')),
            'total_spent' => array_sum(array_column($rows, 'spent')),
        ];
    }

    /**
     * Categories with spending but no budget, so they can be added quickly.
     */
    public function unbudgetedGroups(string $period): Collection
    {
        $budgetedIds = Budget::forUser($this->userId)->forPeriod($period)->pluck('category_id')->all();

        return Category::forUser($this->userId)
            ->ofType('expense')
            ->roots()
            ->whereNotIn('id', $budgetedIds)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array{value: float, cost: float, gain: float, gain_percent: float, count: int}
     */
    public function portfolio(): array
    {
        $holdings = Holding::forUser($this->userId)->get();

        $value = $holdings->sum(fn (Holding $h) => $h->market_value);
        $cost = $holdings->sum(fn (Holding $h) => $h->cost_basis);
        $gain = $value - $cost;

        return [
            'value' => $value,
            'cost' => $cost,
            'gain' => $gain,
            'gain_percent' => $cost > 0 ? round(($gain / $cost) * 100, 2) : 0.0,
            'count' => $holdings->count(),
        ];
    }

    /**
     * @return array{target: float, saved: float, remaining: float, percent: float, count: int}
     */
    public function savings(): array
    {
        $goals = SavingsGoal::forUser($this->userId)->where('is_completed', false)->get();

        $target = (float) $goals->sum('target_amount');
        $saved = (float) $goals->sum('saved_amount');

        return [
            'target' => $target,
            'saved' => $saved,
            'remaining' => max(0, $target - $saved),
            'percent' => $target > 0 ? round(($saved / $target) * 100, 1) : 0.0,
            'count' => $goals->count(),
        ];
    }

    /**
     * Flatten the report into spreadsheet rows: [group, category, amount, group subtotal].
     *
     * @return array<int, array{0: string, 1: string, 2: float|string, 3: float|string}>
     */
    public function spreadsheetRows(string $period): array
    {
        $rows = [];
        $grand = ['income' => 0.0, 'expense' => 0.0];

        foreach (['income', 'expense'] as $type) {
            $report = $this->hierarchicalReport($period, $type);
            $sectionTotal = $report->sum('total');
            $grand[$type] = $sectionTotal;

            foreach ($report as $group) {
                $children = array_filter($group['children'], fn ($c) => $c['amount'] > 0);
                $isFirst = true;

                if (empty($children)) {
                    $rows[] = [ucfirst($type), $group['group']->name, '', ''];
                    $isFirst = false;
                }

                foreach ($children as $child) {
                    $rows[] = [
                        $isFirst ? ucfirst($type) : '',
                        $group['group']->name,
                        $child['name'],
                        $child['amount'],
                        $isFirst ? $group['total'] : '',
                    ];
                    $isFirst = false;
                }
            }

            $rows[] = ['', '', strtoupper($type).' TOTAL', $sectionTotal];
            $rows[] = ['', '', '', ''];
        }

        $rows[] = ['', '', 'NET', $grand['income'] - $grand['expense']];

        return $rows;
    }

    /**
     * Every transaction the user has, for the full spreadsheet export.
     */
    public function ledgerRows(): array
    {
        $rows = [['Date', 'Month', 'Type', 'Group', 'Category', 'Note', 'Source', 'Amount']];

        $transactions = Transaction::forUser($this->userId)
            ->with(['category.parent'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        foreach ($transactions as $tx) {
            $rows[] = [
                $tx->date->format('d/m/Y'),
                $tx->date->format('M Y'),
                ucfirst($tx->type),
                $tx->category?->parent?->name ?? '',
                $tx->category?->name ?? 'Uncategorised',
                $tx->description ?? '',
                $tx->source,
                (float) $tx->amount,
            ];
        }

        return $rows;
    }

    /**
     * One row per month that has activity, plus a grand total.
     */
    public function monthlyTotalsRows(): array
    {
        $periods = Transaction::forUser($this->userId)
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as period")
            ->groupBy('period')
            ->orderByDesc('period')
            ->pluck('period');

        $rows = [['Month', 'Income', 'Expense', 'Net', 'Savings rate']];
        $income = 0.0;
        $expense = 0.0;

        foreach ($periods as $period) {
            $summary = $this->monthlySummary($period);
            $income += $summary['income'];
            $expense += $summary['expense'];

            $rows[] = [
                Carbon::parse($period.'-01')->format('M Y'),
                $summary['income'],
                $summary['expense'],
                $summary['net'],
                $summary['savings_rate'].'%',
            ];
        }

        $net = $income - $expense;

        $rows[] = [
            'TOTAL',
            $income,
            $expense,
            $net,
            ($income > 0 ? round(($net / $income) * 100, 1) : 0).'%',
        ];

        return $rows;
    }
}
