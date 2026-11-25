@forelse ($transactions as $tx)
    <tr class="row-hover">
        <td class="td num whitespace-nowrap text-[11px] text-muted">{{ $tx->date->format('d/m/Y') }}</td>
        <td class="td text-[11px] text-muted">{{ $tx->category?->parent?->name ?? '—' }}</td>
        <td class="td font-medium">{{ $tx->category?->name ?? 'Uncategorised' }}</td>
        <td class="td hidden max-w-[220px] truncate text-[11px] text-muted sm:table-cell">{{ $tx->description ?: '—' }}</td>
        <td class="td hidden md:table-cell">
            <span class="chip">{{ $tx->source }}</span>
        </td>
        <td class="td num text-right font-medium {{ $isIncome ? 'text-pos' : 'text-ink' }}">@money($tx->amount)</td>
        <td class="td">
            <div class="flex items-center justify-end gap-0.5">
                <button type="button" onclick="openTxModal(this)"
                        data-id="{{ $tx->id }}"
                        data-action="{{ route($type.'.update', $tx) }}"
                        data-category="{{ $tx->category_id }}"
                        data-amount="{{ number_format((float) $tx->amount, 0, '', '') }}"
                        data-date="{{ $tx->date->toDateString() }}"
                        data-description="{{ $tx->description }}"
                        class="p-1 text-faint transition hover:text-accent" title="Edit">
                    <x-icon name="edit" class="h-3.5 w-3.5" />
                </button>
                <form method="POST" action="{{ route($type.'.destroy', $tx) }}" onsubmit="event.preventDefault(); askConfirm(this, 'Delete this entry?', { danger: true, label: 'Delete' })">
                    @csrf
                    @method('DELETE')
                    <button class="p-1 text-faint transition hover:text-neg" title="Delete">
                        <x-icon name="trash" class="h-3.5 w-3.5" />
                    </button>
                </form>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="px-4 py-10 text-center">
            <p class="text-[12px] font-medium">Nothing matches</p>
            <p class="mt-0.5 text-[11px] text-faint">Try a different keyword, or clear the filters.</p>
        </td>
    </tr>
@endforelse
