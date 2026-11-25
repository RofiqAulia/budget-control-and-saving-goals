<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);
        $report = ReportService::for($request->user()->id);

        // Leaf-level spending, so the page can show what actually ate the budget.
        $topCategories = Transaction::forUser($request->user()->id)
            ->ofType('expense')
            ->inPeriod($period)
            ->with('category.parent')
            ->get()
            ->groupBy('category_id')
            ->map(fn ($rows) => [
                'name' => $rows->first()->category?->name ?? 'Uncategorised',
                'group' => $rows->first()->category?->parent?->name ?? '—',
                'color' => $rows->first()->category?->parent?->color,
                'total' => (float) $rows->sum('amount'),
            ])
            ->sortByDesc('total')
            ->take(10)
            ->values();

        return view('budgets.index', [
            'period' => $period,
            'overview' => $report->budgetOverview($period),
            'unbudgeted' => $report->unbudgetedGroups($period),
            'groups' => Category::forUser($request->user()->id)->ofType('expense')->roots()->orderBy('sort_order')->get(),
            'topCategories' => $topCategories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => ['required', 'string', 'max:30'],
        ]);

        // Guard: the category must belong to the current user and be an expense group.
        $category = Category::forUser($request->user()->id)
            ->ofType('expense')
            ->whereKey($data['category_id'])
            ->firstOrFail();

        Budget::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'category_id' => $category->id,
                'period' => $data['period'],
            ],
            ['amount' => Money::parse($data['amount'])]
        );

        return redirect()
            ->route('budgets.index', ['period' => $data['period']])
            ->with('status', 'Budget saved for '.$category->name.'.');
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($budget->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'string', 'max:30'],
        ]);

        $budget->update(['amount' => Money::parse($data['amount'])]);

        return redirect()
            ->route('budgets.index', ['period' => $budget->period])
            ->with('status', 'Budget updated.');
    }

    public function destroy(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless($budget->user_id === $request->user()->id, 404);

        $period = $budget->period;
        $budget->delete();

        return redirect()
            ->route('budgets.index', ['period' => $period])
            ->with('status', 'Budget removed.');
    }

    private function resolvePeriod(Request $request): string
    {
        $period = (string) $request->query('period', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)
            ? $period
            : Carbon::now()->format('Y-m');
    }
}
