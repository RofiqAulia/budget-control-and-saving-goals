<?php

namespace App\Http\Controllers;

use App\Models\Debt;
use App\Models\SavingsGoal;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavingsController extends Controller
{
    public function index(Request $request): View
    {
        $goals = SavingsGoal::forUser($request->user()->id)
            ->orderBy('is_completed')
            ->orderByDesc('saved_amount')
            ->get();

        return view('savings.index', [
            'goals' => $goals,
            'totalTarget' => (float) $goals->where('is_completed', false)->sum('target_amount'),
            'totalSaved' => (float) $goals->where('is_completed', false)->sum('saved_amount'),
            'debt' => Debt::summaryFor($request->user()->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        SavingsGoal::create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('savings.index')->with('status', 'Savings goal created.');
    }

    public function update(Request $request, SavingsGoal $goal): RedirectResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        $data = $this->validated($request);

        $goal->update($data + [
            'is_completed' => (float) $data['saved_amount'] >= (float) $data['target_amount'],
        ]);

        return redirect()->route('savings.index')->with('status', 'Savings goal updated.');
    }

    public function destroy(Request $request, SavingsGoal $goal): RedirectResponse
    {
        abort_unless($goal->user_id === $request->user()->id, 404);

        $goal->delete();

        return redirect()->route('savings.index')->with('status', 'Savings goal deleted.');
    }

    /**
     * @return array{name: string, target_amount: float, saved_amount: float, target_date: ?string, note: ?string}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'target_amount' => ['required', 'string', 'max:30'],
            'saved_amount' => ['nullable', 'string', 'max:30'],
            'target_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'name' => $data['name'],
            'target_amount' => Money::parse($data['target_amount']),
            'saved_amount' => Money::parse($data['saved_amount'] ?? '0'),
            'target_date' => $data['target_date'] ?? null,
            'note' => $data['note'] ?? null,
        ];
    }
}
