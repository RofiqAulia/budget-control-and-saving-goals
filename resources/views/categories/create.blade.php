@php $label = ucfirst($type); @endphp

<x-app-layout :title="'Add ' . $label . ' category'" subtitle="New group or sub-category">
    <div class="grid items-start gap-3 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="panel">
                <div class="panel-head">
                    <h2 class="text-[12px] font-medium">Category details</h2>
                    <span class="text-[10px] text-faint">{{ $label }}</span>
                </div>
                <form method="POST" action="{{ route($type.'.categories.store') }}" class="p-3">
                    @csrf
                    @include('categories._form', ['submitLabel' => 'Save category'])
                </form>
            </div>
        </div>

        @include('categories._groups', ['groups' => $groups, 'title' => 'Existing groups'])
    </div>
</x-app-layout>
