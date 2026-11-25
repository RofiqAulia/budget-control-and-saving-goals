<x-app-layout title="Portfolio" subtitle="Holdings and market value">
    <x-slot:actions>
        <button type="button" onclick="openModal('holding-modal')" class="btn btn-primary">
            <x-icon name="plus" class="h-3 w-3" /> Add holding
        </button>
    </x-slot:actions>

    <div class="panel">
        <div class="grid grid-cols-2 divide-x divide-line lg:grid-cols-4">
            <div class="border-b border-line p-3 lg:border-b-0">
                <p class="lbl">Market value</p>
                <p class="num mt-1 text-[17px] font-medium">@money($summary['value'])</p>
            </div>
            <div class="border-b border-line p-3 lg:border-b-0">
                <p class="lbl">Cost basis</p>
                <p class="num mt-1 text-[17px] font-medium text-muted">@money($summary['cost'])</p>
            </div>
            <div class="border-b border-line p-3 lg:border-b-0">
                <p class="lbl">Unrealised gain</p>
                <p class="num mt-1 text-[17px] font-medium {{ $summary['gain'] >= 0 ? 'text-pos' : 'text-neg' }}">
                    {{ $summary['gain'] >= 0 ? '+' : '' }}@money($summary['gain'])
                </p>
            </div>
            <div class="p-3">
                <p class="lbl">Return</p>
                <p class="num mt-1 text-[17px] font-medium {{ $summary['gain_percent'] >= 0 ? 'text-pos' : 'text-neg' }}">{{ $summary['gain_percent'] }}%</p>
            </div>
        </div>
    </div>

    <x-modal id="holding-modal" title="Add holding">
        <form method="POST" action="{{ route('portfolio.store') }}">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="lbl mb-1 block">Symbol</label>
                    <input name="symbol" required maxlength="30" placeholder="BBCA" class="field uppercase">
                </div>
                <div>
                    <label class="lbl mb-1 block">Name</label>
                    <input name="name" required maxlength="120" placeholder="Bank Central Asia" class="field">
                </div>
                <div>
                    <label class="lbl mb-1 block">Asset type</label>
                    <select name="asset_type" class="field">
                        @foreach (\App\Models\Holding::ASSET_TYPES as $type)
                            <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="lbl mb-1 block">Quantity</label>
                    <input name="quantity" required inputmode="decimal" placeholder="10" class="field num">
                </div>
                <div>
                    <label class="lbl mb-1 block">Average price</label>
                    <input name="avg_price" required inputmode="numeric" placeholder="9.200" class="field num">
                </div>
                <div>
                    <label class="lbl mb-1 block">Current price <span class="normal-case tracking-normal">(optional)</span></label>
                    <input name="current_price" inputmode="numeric" placeholder="10.150" class="field num">
                </div>
            </div>

            <div class="mt-4 flex items-center gap-1.5 border-t border-line pt-3">
                <button class="btn btn-primary">Save holding</button>
                <button type="button" onclick="closeModal('holding-modal')" class="btn">Cancel</button>
            </div>
        </form>
    </x-modal>

    <div class="panel mt-3">
        <div class="panel-head">
            <h2 class="text-[12px] font-medium">Holdings</h2>
            <div class="flex flex-wrap gap-1">
                @foreach ($byType as $type => $data)
                    <span class="chip">{{ ucwords(str_replace('_', ' ', $type)) }} · @money($data['value'])</span>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="th">Asset</th>
                        <th class="th hidden sm:table-cell">Type</th>
                        <th class="th text-right">Qty</th>
                        <th class="th text-right">Avg</th>
                        <th class="th text-right">Price</th>
                        <th class="th text-right">Value</th>
                        <th class="th text-right">Gain</th>
                        <th class="th w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($holdings as $holding)
                        <tr class="row-hover">
                            <td class="td">
                                <div class="flex items-baseline gap-1.5">
                                    <span class="num font-medium">{{ $holding->symbol }}</span>
                                    <span class="truncate text-[10px] text-faint">{{ $holding->name }}</span>
                                </div>
                            </td>
                            <td class="td hidden text-[11px] text-muted sm:table-cell">{{ ucwords(str_replace('_', ' ', $holding->asset_type)) }}</td>
                            <td class="td num text-right text-muted">{{ rtrim(rtrim(number_format((float) $holding->quantity, 8, ',', '.'), '0'), ',') }}</td>
                            <td class="td num text-right text-muted">@money($holding->avg_price)</td>
                            <td class="td num text-right text-muted">@money($holding->price)</td>
                            <td class="td num text-right font-medium">@money($holding->market_value)</td>
                            <td class="td num text-right {{ $holding->gain >= 0 ? 'text-pos' : 'text-neg' }}">
                                {{ $holding->gain >= 0 ? '+' : '' }}@money($holding->gain)
                                <span class="block text-[10px] text-faint">{{ $holding->gain_percent }}%</span>
                            </td>
                            <td class="td">
                                <form method="POST" action="{{ route('portfolio.destroy', $holding) }}" onsubmit="event.preventDefault(); askConfirm(this, 'Remove this holding?', { danger: true, label: 'Delete' })">
                                    @csrf
                                    @method('DELETE')
                                    <button class="p-1 text-faint transition hover:text-neg" title="Remove">
                                        <x-icon name="trash" class="h-3.5 w-3.5" />
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center">
                                <p class="text-[12px] font-medium">No holdings</p>
                                <p class="mt-0.5 text-[11px] text-faint">Add one to see portfolio value on the dashboard.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
