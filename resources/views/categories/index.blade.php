@php
    $label = ucfirst($type);
@endphp

<x-app-layout :title="($scope ?? null) ? $scope . ' categories' : $label . ' categories'" :subtitle="($scope ?? null) ? 'Sub-categories under ' . $scope : 'Groups and sub-categories used by ' . strtolower($label) . ' entries'">
    <x-slot:actions>
        <button type="button" onclick="openCatModal()" class="btn btn-primary">
            <x-icon name="plus" class="h-3 w-3" /> Add category
        </button>
    </x-slot:actions>

    <div class="panel">
        <div class="panel-head">
            <h2 class="text-[12px] font-medium">Categories</h2>
            <span class="num text-[10px] text-faint">{{ $groupCount }} groups · {{ $leafCount }} sub-categories</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="th">Category</th>
                        <th class="th w-44">Group</th>
                        <th class="th w-32 text-right">This month</th>
                        <th class="th w-24"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $category = $row['category']; @endphp

                        @if ($row['is_group'])
                            <tr class="row-hover bg-canvas/60">
                                <td class="td">
                                    <div class="flex items-center gap-1.5">
                                        <span class="h-2 w-2 shrink-0" style="background: {{ $category->color ?: '#185fa5' }}"></span>
                                        <span class="font-medium">{{ $category->name }}</span>
                                        <span class="chip ml-1">{{ $row['children'] }}</span>
                                    </div>
                                </td>
                                <td class="td text-[11px] text-faint">—</td>
                                <td class="td num text-right font-medium">@money($row['total'])</td>
                                <td class="td">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <button type="button" onclick="openCatModal(this)" data-parent="{{ $category->id }}"
                                                class="p-1 text-faint transition hover:text-accent" title="Add sub-category">
                                            <x-icon name="plus" class="h-3.5 w-3.5" />
                                        </button>
                                        <button type="button" onclick="openCatModal(this)"
                                                data-id="{{ $category->id }}"
                                                data-action="{{ route($type.'.categories.update', $category) }}"
                                                data-name="{{ $category->name }}"
                                                data-group="1"
                                                data-color="{{ $category->color }}"
                                                class="p-1 text-faint transition hover:text-accent" title="Edit">
                                            <x-icon name="edit" class="h-3.5 w-3.5" />
                                        </button>
                                        <form method="POST" action="{{ route($type.'.categories.destroy', $category) }}"
                                              onsubmit="event.preventDefault(); askConfirm(this, 'Delete this group and all its sub-categories? Transactions are kept as uncategorised.', { danger: true, label: 'Delete' })">
                                            @csrf
                                            @method('DELETE')
                                            <button class="p-1 text-faint transition hover:text-neg" title="Delete">
                                                <x-icon name="trash" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @else
                            <tr class="row-hover">
                                <td class="td">
                                    <span class="pl-4 text-faint">·</span>
                                    <span class="ml-1">{{ $category->name }}</span>
                                </td>
                                <td class="td text-[11px] text-muted">{{ $row['parent']?->name ?? '—' }}</td>
                                <td class="td num text-right text-muted">@money($row['total'])</td>
                                <td class="td">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <button type="button" onclick="openCatModal(this)"
                                                data-id="{{ $category->id }}"
                                                data-action="{{ route($type.'.categories.update', $category) }}"
                                                data-name="{{ $category->name }}"
                                                data-parent="{{ $category->parent_id }}"
                                                data-group="0"
                                                data-color="{{ $category->color }}"
                                                class="p-1 text-faint transition hover:text-accent" title="Edit">
                                            <x-icon name="edit" class="h-3.5 w-3.5" />
                                        </button>
                                        <form method="POST" action="{{ route($type.'.categories.destroy', $category) }}"
                                              onsubmit="event.preventDefault(); askConfirm(this, 'Delete this sub-category? Transactions are kept as uncategorised.', { danger: true, label: 'Delete' })">
                                            @csrf
                                            @method('DELETE')
                                            <button class="p-1 text-faint transition hover:text-neg" title="Delete">
                                                <x-icon name="trash" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center">
                                <p class="text-[12px] font-medium">No categories yet</p>
                                <p class="mt-0.5 text-[11px] text-faint">Create your first group to start recording {{ strtolower($label) }}.</p>
                                <button type="button" onclick="openCatModal()" class="btn btn-primary mt-2">
                                    <x-icon name="plus" class="h-3 w-3" /> Add category
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-2 px-1 text-[10px] leading-relaxed text-faint">
        Amounts are this month's {{ strtolower($label) }} per category. Deleting a group also removes its sub-categories; existing transactions stay but become uncategorised.
    </p>

    <x-modal id="cat-modal" title="Add category">
        <form id="cat-form" method="POST" action="{{ route($type.'.categories.store') }}">
            @csrf
            <input type="hidden" name="_method" value="POST" id="cat-method">

            <div class="space-y-3">
                <div>
                    <label for="cat-name" class="lbl mb-1 block">Name</label>
                    <input id="cat-name" name="name" required maxlength="60"
                           class="field" placeholder="e.g. {{ $type === 'income' ? 'Bonus' : 'Health' }}">
                </div>

                <div id="cat-parent-wrap">
                    <label for="cat-parent" class="lbl mb-1 block">Parent group</label>
                    <select id="cat-parent" name="parent_id" class="field">
                        <option value="">— New top-level group —</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[10px] text-faint">Leave empty to create a group; pick a group to create a sub-category.</p>
                </div>

                <div id="cat-color-wrap">
                    <label for="cat-color" class="lbl mb-1 block">Colour</label>
                    <input id="cat-color" type="color" name="color" value="#185fa5"
                           class="h-7 w-14 cursor-pointer border border-line-strong bg-white p-0">
                    <p class="mt-1 text-[10px] text-faint">Used for the dot in reports.</p>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-1.5 border-t border-line pt-3">
                <button class="btn btn-primary">Save category</button>
                <button type="button" onclick="closeModal('cat-modal')" class="btn">Cancel</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="saved-modal" title="Saved">
        <p class="text-[12px] leading-relaxed text-muted">The category was saved. What would you like to do next?</p>

        <div class="mt-4 flex items-center gap-1.5 border-t border-line pt-3">
            <button type="button" class="btn btn-primary" onclick="closeModal('saved-modal'); openCatModal();">
                Add another
            </button>
            <button type="button" class="btn" onclick="closeModal('saved-modal')">Back to list</button>
        </div>
    </x-modal>

    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('cat-form');
                const method = document.getElementById('cat-method');
                const title = document.querySelector('#cat-modal [data-modal-title]');
                const nameInput = document.getElementById('cat-name');
                const parentSelect = document.getElementById('cat-parent');
                const parentWrap = document.getElementById('cat-parent-wrap');
                const colorInput = document.getElementById('cat-color');
                const colorWrap = document.getElementById('cat-color-wrap');
                const submit = form.querySelector('.btn-primary');
                const storeAction = @json(route($type . '.categories.store'));

                // Colour only matters for a top-level group.
                function syncColor() {
                    colorWrap.classList.toggle('hidden', parentSelect.value !== '');
                }

                parentSelect.addEventListener('change', syncColor);

                window.openCatModal = function (button) {
                    form.reset();
                    const editing = button && button.dataset.id;

                    if (editing) {
                        const isGroup = button.dataset.group === '1';
                        form.action = button.dataset.action;
                        method.value = 'PUT';
                        nameInput.value = button.dataset.name;
                        colorInput.value = button.dataset.color || '#185fa5';

                        parentWrap.classList.toggle('hidden', isGroup);
                        parentSelect.value = isGroup ? '' : (button.dataset.parent || '');

                        colorWrap.classList.toggle('hidden', !isGroup);
                        title.textContent = isGroup ? 'Edit group' : 'Edit sub-category';
                        submit.textContent = 'Update category';
                    } else {
                        form.action = storeAction;
                        method.value = 'POST';
                        parentSelect.value = (button && button.dataset.parent) || '';
                        colorInput.value = '#185fa5';
                        parentWrap.classList.remove('hidden');
                        syncColor();
                        title.textContent = 'Add category';
                        submit.textContent = 'Save category';
                    }

                    openModal('cat-modal');
                };

                form.addEventListener('submit', function (event) {
                    if (method.value !== 'PUT') return;

                    event.preventDefault();
                    askConfirm(form, 'Save the changes to this category?', { label: 'Save changes' });
                });

                if (new URLSearchParams(window.location.search).has('new')) {
                    window.openCatModal();
                }

                if (new URLSearchParams(window.location.search).has('created')) {
                    openModal('saved-modal');

                    const url = new URL(window.location.href);
                    url.searchParams.delete('created');
                    window.history.replaceState({}, '', url);
                }
            })();
        </script>
    @endpush
</x-app-layout>
