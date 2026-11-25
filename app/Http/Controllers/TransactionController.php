<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $type = $this->type($request);
        $period = $this->resolvePeriod($request);

        $query = Transaction::forUser($request->user()->id)
            ->ofType($type)
            ->with(['category.parent']);

        if ($request->filled('period')) {
            $query->inPeriod($period);
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        // One search box covers every visible column.
        if ($request->filled('q')) {
            $search = trim($request->string('q')->toString());
            $like = "%{$search}%";

            $query->where(function ($q) use ($like) {
                $q->where('description', 'like', $like)
                    ->orWhere('source', 'like', $like)
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('category.parent', fn ($c) => $c->where('name', 'like', $like))
                    ->orWhereRaw('CAST(transactions.amount AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw("DATE_FORMAT(transactions.date, '%d/%m/%Y') LIKE ?", [$like])
                    ->orWhereRaw("DATE_FORMAT(transactions.date, '%Y-%m-%d') LIKE ?", [$like]);
            });
        }

        // Sortable columns: clicking a header toggles asc/desc.
        $sortable = [
            'date' => 'transactions.date',
            'group' => 'parents.name',
            'category' => 'categories.name',
            'description' => 'transactions.description',
            'source' => 'transactions.source',
            'amount' => 'transactions.amount',
        ];

        $sort = array_key_exists((string) $request->query('sort'), $sortable) ? (string) $request->query('sort') : 'date';
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, ['category', 'group'], true)) {
            // Ordering by a related column needs the categories table in the query.
            $query->leftJoin('categories as categories', 'categories.id', '=', 'transactions.category_id')
                ->leftJoin('categories as parents', 'parents.id', '=', 'categories.parent_id')
                ->select('transactions.*');
        }

        $query->orderBy($sortable[$sort], $dir)->orderBy('transactions.id', $dir);

        // Total follows the active filters so the header always matches the rows.
        $filteredTotal = (float) (clone $query)->sum('transactions.amount');

        $transactions = $query->paginate(15)->withQueryString();

        if ($request->boolean('ajax')) {
            return response()->json([
                'rows' => view('transactions._rows', [
                    'transactions' => $transactions,
                    'type' => $type,
                    'isIncome' => $type === 'income',
                ])->render(),
                'pagination' => $transactions->hasPages()
                    ? $transactions->links('pagination.ledger')->render()
                    : '',
                'total' => Money::format($filteredTotal),
                'count' => $transactions->total(),
                'first' => $transactions->firstItem(),
                'last' => $transactions->lastItem(),
                'sort' => $sort,
                'dir' => $dir,
            ]);
        }

        $categories = Category::forUser($request->user()->id)
            ->ofType($type)
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->roots()
            ->orderBy('sort_order')
            ->get();

        return view('transactions.index', [
            'type' => $type,
            'period' => $period,
            'transactions' => $transactions,
            'filteredTotal' => $filteredTotal,
            'categories' => $categories,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View
    {
        $type = $this->type($request);

        return view('transactions.create', [
            'type' => $type,
            'transaction' => new Transaction([
                'type' => $type,
                'date' => Carbon::now()->toDateString(),
            ]),
            'categories' => $this->categoryTree($request, $type),
            'context' => $this->context($request, $type),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->type($request);
        $data = $this->validated($request, $type);

        Transaction::create($data + [
            'user_id' => $request->user()->id,
            'type' => $type,
            'source' => 'web',
        ]);

        return redirect()
            ->route("{$type}.index", ['created' => 1])
            ->with('status', ucfirst($type).' recorded successfully.');
    }

    public function edit(Request $request, Transaction $transaction): View
    {
        $type = $this->type($request);
        $this->authorizeTransaction($request, $transaction, $type);

        return view('transactions.edit', [
            'type' => $type,
            'transaction' => $transaction,
            'categories' => $this->categoryTree($request, $type),
            'context' => $this->context($request, $type),
        ]);
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $type = $this->type($request);
        $this->authorizeTransaction($request, $transaction, $type);

        $transaction->update($this->validated($request, $type));

        return redirect()
            ->route("{$type}.index")
            ->with('status', ucfirst($type).' updated successfully.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $type = $this->type($request);
        $this->authorizeTransaction($request, $transaction, $type);

        $transaction->delete();

        return redirect()
            ->route("{$type}.index")
            ->with('status', ucfirst($type).' deleted.');
    }

    private function type(Request $request): string
    {
        $type = (string) $request->route('type');

        abort_unless(in_array($type, ['income', 'expense'], true), 404);

        return $type;
    }

    private function resolvePeriod(Request $request): string
    {
        $period = (string) $request->query('period', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)
            ? $period
            : Carbon::now()->format('Y-m');
    }

    private function categoryTree(Request $request, string $type)
    {
        return Category::forUser($request->user()->id)
            ->ofType($type)
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->roots()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Side-panel context: what was logged recently and how the month is tracking.
     *
     * @return array{recent: \Illuminate\Support\Collection, month_total: float, month_count: int, top: \Illuminate\Support\Collection}
     */
    private function context(Request $request, string $type): array
    {
        $userId = $request->user()->id;
        $period = Carbon::now()->format('Y-m');

        $monthQuery = Transaction::forUser($userId)->ofType($type)->inPeriod($period);

        $trend = array_map(
            fn (array $row) => [
                'label' => $row['label'],
                'value' => $type === 'income' ? $row['income'] : $row['expense'],
            ],
            ReportService::for($userId)->growthSeries(6)
        );

        return [
            'recent' => Transaction::forUser($userId)
                ->ofType($type)
                ->with('category')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'month_total' => (float) (clone $monthQuery)->sum('amount'),
            'month_count' => (clone $monthQuery)->count(),
            'trend' => $trend,
            'top' => (clone $monthQuery)
                ->with('category')
                ->get()
                ->groupBy(fn (Transaction $tx) => $tx->category?->name ?? 'Uncategorised')
                ->map(fn ($group, $name) => ['name' => $name, 'total' => (float) $group->sum('amount')])
                ->sortByDesc('total')
                ->take(4)
                ->values(),
        ];
    }

    /**
     * @return array{category_id: int, date: string, amount: float, description: ?string}
     */
    private function validated(Request $request, string $type): array
    {
        $data = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')
                    ->where('user_id', $request->user()->id)
                    ->where('type', $type),
            ],
            'date' => ['required', 'date'],
            'amount' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = Money::parse($data['amount']);

        if ($amount <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'Enter an amount greater than zero.',
            ]);
        }

        return [
            'category_id' => (int) $data['category_id'],
            'date' => $data['date'],
            'amount' => $amount,
            'description' => $data['description'] ?? null,
        ];
    }

    private function authorizeTransaction(Request $request, Transaction $transaction, string $type): void
    {
        abort_unless(
            $transaction->user_id === $request->user()->id && $transaction->type === $type,
            404
        );
    }
}
