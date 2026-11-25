<x-app-layout title="Savings" subtitle="Goals and progress">
    <x-slot:actions>
        <button type="button" onclick="openModal('goal-modal')" class="btn btn-primary">
            <x-icon name="plus" class="h-3 w-3" /> New goal
        </button>
    </x-slot:actions>

    @php $pct = $totalTarget > 0 ? round(($totalSaved / $totalTarget) * 100, 1) : 0; @endphp

    <div class="panel">
        <div class="grid grid-cols-2 divide-x divide-line lg:grid-cols-4">
            <div class="border-b border-line p-3 lg:border-b-0">
                <p class="lbl">Total saved</p>
                <p class="num mt-1 text-[17px] font-medium text-pos">@money($totalSaved)</p>
            </div>
            <div class="border-b border-line p-3 lg:border-b-0">
                <p class="lbl">Total target</p>
                <p class="num mt-1 text-[17px] font-medium">@money($totalTarget)</p>
            </div>
            <div class="border-b border-line p-3 lg:border-b-0">
                <p class="lbl">Overall progress</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <p class="num text-[17px] font-medium">{{ $pct }}%</p>
                    <div class="bar flex-1"><span style="width: {{ min(100, $pct) }}%"></span></div>
                </div>
            </div>
            <div class="p-3">
                <p class="lbl">Debt outstanding</p>
                <p class="num mt-1 text-[17px] font-medium {{ $debt['payable'] > 0 ? 'text-neg' : 'text-muted' }}">@money($debt['payable'])</p>
                <p class="text-[10px] text-faint">
                    @if ($debt['receivable'] > 0)
                        plus @money($debt['receivable']) owed to you
                    @else
                        still to pay
                    @endif
                </p>
            </div>
        </div>
    </div>

    <x-modal id="goal-modal" title="New savings goal">
        <form method="POST" action="{{ route('savings.store') }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="lbl mb-1 block">Name</label>
                    <input name="name" required maxlength="120" placeholder="Dana Darurat" class="field">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="lbl mb-1 block">Target</label>
                        <input name="target_amount" required inputmode="numeric" placeholder="18.000.000" class="field num">
                    </div>
                    <div>
                        <label class="lbl mb-1 block">Saved so far</label>
                        <input name="saved_amount" inputmode="numeric" placeholder="0" class="field num">
                    </div>
                </div>
                <div>
                    <label class="lbl mb-1 block">Deadline <span class="normal-case tracking-normal">(optional)</span></label>
                    <input name="target_date" type="date" class="field num">
                </div>
                <div>
                    <label class="lbl mb-1 block">Note <span class="normal-case tracking-normal">(optional)</span></label>
                    <input name="note" maxlength="255" placeholder="e.g. 6x monthly spending" class="field">
                </div>
            </div>

            <div class="mt-4 flex items-center gap-1.5 border-t border-line pt-3">
                <button class="btn btn-primary">Create goal</button>
                <button type="button" onclick="closeModal('goal-modal')" class="btn">Cancel</button>
            </div>
        </form>
    </x-modal>

    <div class="panel mt-3">
        <div class="panel-head">
            <h2 class="text-[12px] font-medium">Goals</h2>
            <span class="num text-[10px] text-faint">{{ $goals->count() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="th">Goal</th>
                        <th class="th text-right">Saved</th>
                        <th class="th text-right">Target</th>
                        <th class="th text-right">Left</th>
                        <th class="th w-32">Progress</th>
                        <th class="th w-32 text-right">Update saved</th>
                        <th class="th w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($goals as $goal)
                        <tr class="row-hover {{ $goal->is_completed ? 'opacity-60' : '' }}">
                            <td class="td">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-medium">{{ $goal->name }}</span>
                                    @if ($goal->is_completed)
                                        <span class="chip border-pos/40 text-pos">done</span>
                                    @endif
                                </div>
                                <p class="text-[10px] text-faint">
                                    {{ $goal->target_date ? 'target '.$goal->target_date->format('M Y') : 'no deadline' }}
                                    @if ($goal->note) · {{ $goal->note }} @endif
                                </p>
                            </td>
                            <td class="td num text-right font-medium">@money($goal->saved_amount)</td>
                            <td class="td num text-right text-muted">@money($goal->target_amount)</td>
                            <td class="td num text-right text-muted">@money($goal->remaining)</td>
                            <td class="td">
                                <div class="flex items-center gap-1.5">
                                    <div class="bar flex-1"><span style="width: {{ $goal->progress_percent }}%; {{ $goal->is_completed ? 'background:#0f6e56' : '' }}"></span></div>
                                    <span class="num w-9 shrink-0 text-right text-[10px] text-muted">{{ $goal->progress_percent }}%</span>
                                </div>
                            </td>
                            <td class="td">
                                <form method="POST" action="{{ route('savings.update', $goal) }}" class="flex items-center justify-end gap-1">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="name" value="{{ $goal->name }}">
                                    <input type="hidden" name="target_amount" value="{{ (float) $goal->target_amount }}">
                                    <input type="hidden" name="target_date" value="{{ $goal->target_date?->toDateString() }}">
                                    <input name="saved_amount" value="{{ number_format((float) $goal->saved_amount, 0, ',', '.') }}" inputmode="numeric"
                                           class="field num w-24 py-[2px] text-right text-[11px]">
                                    <button class="btn px-1.5 py-[2px]">Set</button>
                                </form>
                            </td>
                            <td class="td">
                                <form method="POST" action="{{ route('savings.destroy', $goal) }}" onsubmit="event.preventDefault(); askConfirm(this, 'Delete this goal?', { danger: true, label: 'Delete' })">
                                    @csrf
                                    @method('DELETE')
                                    <button class="p-1 text-faint transition hover:text-neg" title="Delete">
                                        <x-icon name="trash" class="h-3.5 w-3.5" />
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center">
                                <p class="text-[12px] font-medium">No savings goals</p>
                                <p class="mt-0.5 text-[11px] text-faint">Create one to track progress on the dashboard.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
