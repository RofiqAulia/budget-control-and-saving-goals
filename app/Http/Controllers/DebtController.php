<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Debt;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DebtController extends Controller
{
    public function index(Request $request): View
    {
        $debts = Debt::forUser($request->user()->id)
            ->with('payments')
            ->orderBy('is_settled')
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('remaining_amount')
            ->get();

        return view('debts.index', [
            'debts' => $debts,
            'summary' => Debt::summaryFor($request->user()->id),
            'debtCategories' => $this->debtCategories($request),
            'projection' => Debt::payoffProjection($request->user()->id),
            'composition' => $this->composition($request),
        ]);
    }

    /**
     * Outstanding amount per debt category, biggest first.
     */
    private function composition(Request $request): \Illuminate\Support\Collection
    {
        return Debt::forUser($request->user()->id)
            ->where('is_settled', false)
            ->where('type', Debt::DEBT)
            ->with('category')
            ->get()
            ->groupBy(fn (Debt $debt) => $debt->category?->name ?? $debt->name)
            ->map(fn ($rows, $name) => [
                'name' => $name,
                'total' => (float) $rows->sum('remaining_amount'),
                'color' => $rows->first()->category?->color ?? '#b91c1c',
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Sub-categories under the "Debts" group; the add form picks from these.
     */
    private function debtCategories(Request $request): \Illuminate\Support\Collection
    {
        $group = Category::forUser($request->user()->id)
            ->ofType('expense')
            ->roots()
            ->whereRaw('LOWER(name) = ?', ['debts'])
            ->first();

        if (! $group) {
            return collect();
        }

        return $group->children()->orderBy('sort_order')->get();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($data['remaining_amount'] === null) {
            $data['remaining_amount'] = $data['principal_amount'];
        }

        Debt::create($data + [
            'user_id' => $request->user()->id,
            'is_settled' => $data['remaining_amount'] <= 0,
        ]);

        return redirect()->route('debts.index')->with('status', "\"{$data['name']}\" added.");
    }

    public function update(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->user_id === $request->user()->id, 404);

        $data = $this->validated($request);

        if ($data['remaining_amount'] === null) {
            $data['remaining_amount'] = $debt->remaining_amount;
        }

        $debt->update($data + [
            'is_settled' => $data['remaining_amount'] <= 0,
        ]);

        return redirect()->route('debts.index')->with('status', "\"{$data['name']}\" updated.");
    }

    public function pay(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'string', 'max:30'],
            'paid_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = Money::parse($data['amount']);

        if ($amount <= 0) {
            return back()->with('error', 'Payment amount must be greater than zero.');
        }

        // Never pay more than what is left.
        $applied = min($amount, $debt->remaining_amount);
        $remaining = round($debt->remaining_amount - $applied, 2);

        $debt->update([
            'remaining_amount' => $remaining,
            'is_settled' => $remaining <= 0,
        ]);

        $debt->payments()->create([
            'amount' => $applied,
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
            'note' => $data['note'] ?? null,
        ]);

        $message = $remaining <= 0
            ? "\"{$debt->name}\" is settled."
            : 'Payment of '.Money::format($applied).' recorded for "'.$debt->name.'".';

        return redirect()->route('debts.index')->with('status', $message);
    }

    public function destroy(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->user_id === $request->user()->id, 404);

        $name = $debt->name;
        $debt->delete();

        return redirect()->route('debts.index')->with('status', "\"{$name}\" deleted.");
    }

    /**
     * @return array{name: string, category_id: ?int, type: string, principal_amount: float, remaining_amount: ?float, interest_rate: ?float, minimum_payment: ?float, due_date: ?string, note: ?string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in([Debt::DEBT, Debt::RECEIVABLE])],
            'principal_amount' => ['required', 'string', 'max:30'],
            'remaining_amount' => ['nullable', 'string', 'max:30'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_payment' => ['nullable', 'string', 'max:30'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $category = null;

        if (! empty($data['category_id'])) {
            $category = Category::forUser($request->user()->id)
                ->whereKey($data['category_id'])
                ->first();

            if (! $category) {
                throw ValidationException::withMessages([
                    'category_id' => 'That category no longer exists.',
                ]);
            }
        }

        // The category provides the name; free text is only a fallback for old rows.
        $name = $category?->name ?? trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'category_id' => 'Pick a debt category, or add one first.',
            ]);
        }

        return [
            'name' => $name,
            'category_id' => $category?->id,
            'type' => $data['type'],
            'principal_amount' => Money::parse($data['principal_amount']),
            'remaining_amount' => filled($data['remaining_amount'] ?? null)
                ? Money::parse($data['remaining_amount'])
                : null,
            'interest_rate' => isset($data['interest_rate']) ? (float) $data['interest_rate'] : null,
            'minimum_payment' => filled($data['minimum_payment'] ?? null)
                ? Money::parse($data['minimum_payment'])
                : null,
            'due_date' => $data['due_date'] ?? null,
            'note' => $data['note'] ?? null,
        ];
    }
}
