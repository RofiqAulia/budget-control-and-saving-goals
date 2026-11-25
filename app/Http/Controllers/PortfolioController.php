<?php

namespace App\Http\Controllers;

use App\Models\Holding;
use App\Services\ReportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function index(Request $request): View
    {
        $holdings = Holding::forUser($request->user()->id)
            ->orderByDesc('asset_type')
            ->orderBy('symbol')
            ->get();

        $byType = $holdings->groupBy('asset_type')->map(fn ($group) => [
            'value' => $group->sum(fn (Holding $h) => $h->market_value),
            'count' => $group->count(),
        ]);

        return view('portfolio.index', [
            'holdings' => $holdings,
            'summary' => ReportService::for($request->user()->id)->portfolio(),
            'byType' => $byType,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Holding::create($this->validated($request) + ['user_id' => $request->user()->id]);

        return redirect()->route('portfolio.index')->with('status', 'Holding added.');
    }

    public function update(Request $request, Holding $holding): RedirectResponse
    {
        abort_unless($holding->user_id === $request->user()->id, 404);

        $holding->update($this->validated($request));

        return redirect()->route('portfolio.index')->with('status', 'Holding updated.');
    }

    public function destroy(Request $request, Holding $holding): RedirectResponse
    {
        abort_unless($holding->user_id === $request->user()->id, 404);

        $holding->delete();

        return redirect()->route('portfolio.index')->with('status', 'Holding removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'asset_type' => ['required', Rule::in(Holding::ASSET_TYPES)],
            'quantity' => ['required', 'string', 'max:30'],
            'avg_price' => ['required', 'string', 'max:30'],
            'current_price' => ['nullable', 'string', 'max:30'],
        ]);

        return [
            'symbol' => strtoupper($data['symbol']),
            'name' => $data['name'],
            'asset_type' => $data['asset_type'],
            'quantity' => (float) str_replace(',', '.', preg_replace('/[^0-9.,]/', '', $data['quantity'])),
            'avg_price' => Money::parse($data['avg_price']),
            'current_price' => isset($data['current_price']) && $data['current_price'] !== ''
                ? Money::parse($data['current_price'])
                : null,
        ];
    }
}
