@php
    $showParent = ! $category->exists || ! is_null($category->parent_id);
    $isGroup = ! $category->exists || is_null($category->parent_id);
    $selectedParent = old('parent_id', $category->parent_id ?? request('parent'));
@endphp

<div class="grid gap-3 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="lbl mb-1 block">Name</label>
        <input id="name" name="name" value="{{ old('name', $category->name) }}" required maxlength="60"
               class="field" placeholder="e.g. {{ $type === 'income' ? 'Bonus' : 'Health' }}">
    </div>

    @if ($showParent)
        <div>
            <label for="parent_id" class="lbl mb-1 block">Parent group</label>
            <select id="parent_id" name="parent_id" class="field">
                <option value="">— New top-level group —</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}" @selected($selectedParent == $group->id)>{{ $group->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[10px] text-faint">Leave empty to create a group; pick a group to create a sub-category.</p>
        </div>
    @endif

    @if ($isGroup)
        <div>
            <label for="color" class="lbl mb-1 block">Colour</label>
            <input id="color" type="color" name="color" value="{{ old('color', $category->color ?: '#185fa5') }}"
                   class="h-7 w-14 cursor-pointer border border-line-strong bg-white p-0">
            <p class="mt-1 text-[10px] text-faint">Used for the dot in reports. Ignored when you pick a parent group.</p>
        </div>
    @endif
</div>

<div class="mt-4 flex items-center gap-1.5 border-t border-line pt-3">
    <button class="btn btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route($type.'.categories') }}" class="btn">Cancel</a>
</div>
