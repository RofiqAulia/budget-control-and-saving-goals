@props(['id', 'title', 'width' => 'max-w-lg'])

<div id="{{ $id }}" data-modal class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
    <div class="absolute inset-0 bg-ink/30" onclick="closeModal('{{ $id }}')"></div>

    <div class="absolute left-1/2 top-1/2 w-full {{ $width }} -translate-x-1/2 -translate-y-1/2 px-3">
        <div class="panel">
            <div class="panel-head">
                <h2 id="{{ $id }}-title" class="text-[12px] font-medium" data-modal-title>{{ $title }}</h2>
                <button type="button" onclick="closeModal('{{ $id }}')" class="p-0.5 text-faint transition hover:text-ink" title="Close">
                    <x-icon name="close" class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="max-h-[75vh] overflow-y-auto p-3">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
