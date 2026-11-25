<div class="panel">
    <div class="panel-head">
        <h2 class="text-[12px] font-medium">{{ $title }}</h2>
        <span class="num text-[10px] text-faint">{{ $groups->count() }}</span>
    </div>

    <table class="w-full">
        <tbody>
            @forelse ($groups as $group)
                <tr class="row-hover border-b border-[#edf0f3] last:border-0">
                    <td class="td">
                        <div class="flex items-center gap-1.5">
                            <span class="h-2 w-2 shrink-0" style="background: {{ $group->color ?: '#185fa5' }}"></span>
                            <span class="text-[11px]">{{ $group->name }}</span>
                        </div>
                    </td>
                    <td class="td num text-right text-[10px] text-faint">{{ $group->children->count() }}</td>
                </tr>
            @empty
                <tr><td class="py-6 text-center text-[11px] text-faint">No groups yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="border-t border-line bg-canvas px-3 py-[5px] text-[10px] leading-relaxed text-faint">
        Groups are the rows of your report. Sub-categories are what you pick when recording an entry.
    </p>
</div>
