@php
    $selected = (int) old('category_id', $transaction->category_id);
    $amountValue = old('amount', $transaction->exists ? number_format((float) $transaction->amount, 0, ',', '.') : '');
    $dateValue = old('date', $transaction->date?->toDateString() ?? now()->toDateString());
@endphp

<div class="grid gap-3 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="category_id" class="lbl mb-1 block">Category</label>
        <select id="category_id" name="category_id" required class="field">
            <option value="">Select a category…</option>
            @foreach ($categories as $group)
                <optgroup label="{{ $group->name }}">
                    @if ($group->children->isEmpty())
                        <option value="{{ $group->id }}" @selected($selected === $group->id)>{{ $group->name }}</option>
                    @else
                        @foreach ($group->children as $child)
                            <option value="{{ $child->id }}" @selected($selected === $child->id)>{{ $child->name }}</option>
                        @endforeach
                    @endif
                </optgroup>
            @endforeach
        </select>
        <p class="mt-1 text-[10px] text-faint">Groups mirror the spreadsheet report structure.</p>
    </div>

    <div>
        <label for="amount" class="lbl mb-1 block">Amount (IDR)</label>
        <div class="flex items-stretch">
            <span class="flex items-center border border-r-0 border-line-strong bg-canvas px-2 text-[11px] text-muted">Rp</span>
            <input id="amount" name="amount" type="text" inputmode="numeric" value="{{ $amountValue }}" required
                   placeholder="0" class="field num border-l-0 text-[13px]">
        </div>
        <p class="mt-1 text-[10px] text-faint">Accepts 5400000 or 5.400.000</p>
    </div>

    <div>
        <label for="date" class="lbl mb-1 block">Date</label>
        <input id="date" name="date" type="date" value="{{ $dateValue }}" required class="field num">
    </div>

    <div class="sm:col-span-2">
        <label for="description" class="lbl mb-1 block">Note <span class="normal-case tracking-normal text-faint">(optional)</span></label>
        <input id="description" name="description" type="text" value="{{ old('description', $transaction->description) }}"
               maxlength="255" placeholder="e.g. Makan siang di kantin" class="field">
    </div>
</div>

<div class="mt-4 flex items-center gap-1.5 border-t border-line pt-3">
    <button class="btn btn-primary">{{ $submitLabel }}</button>
    @if (! empty($cancelAsButton))
        <button type="button" onclick="closeModal('{{ $modalId }}')" class="btn">Cancel</button>
    @else
        <a href="{{ route($type.'.index') }}" class="btn">Cancel</a>
    @endif
</div>
