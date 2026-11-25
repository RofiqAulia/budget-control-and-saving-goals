<x-app-layout :title="'Add ' . ucfirst($type)" subtitle="New entry">
    <div class="grid items-start gap-3 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="panel">
                <div class="panel-head">
                    <h2 class="text-[12px] font-medium">{{ ucfirst($type) }} details</h2>
                    <span class="num text-[10px] text-faint">{{ now()->format('d/m/Y') }}</span>
                </div>
                <form method="POST" action="{{ route($type.'.store') }}" class="p-3">
                    @csrf
                    @include('transactions._form', ['submitLabel' => 'Save '.$type])
                </form>
            </div>
        </div>

        @include('transactions._context', ['context' => $context, 'type' => $type])
    </div>
</x-app-layout>
