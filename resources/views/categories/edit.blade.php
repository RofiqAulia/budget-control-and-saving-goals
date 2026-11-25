@php $label = ucfirst($type); @endphp

<x-app-layout :title="'Edit ' . $label . ' category'" :subtitle="$category->parent ? 'Sub-category of ' . $category->parent->name : 'Top-level group'">
    <div class="grid items-start gap-3 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="panel">
                <div class="panel-head">
                    <h2 class="text-[12px] font-medium">Category details</h2>
                    <span class="num text-[10px] text-faint">#{{ $category->id }}</span>
                </div>
                <form method="POST" action="{{ route($type.'.categories.update', $category) }}" class="p-3">
                    @csrf
                    @method('PUT')
                    @include('categories._form', ['submitLabel' => 'Update category'])
                </form>
            </div>
        </div>

        @include('categories._groups', ['groups' => $groups, 'title' => 'Other groups'])
    </div>
</x-app-layout>
