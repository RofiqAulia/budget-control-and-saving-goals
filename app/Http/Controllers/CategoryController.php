<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $type = $this->type($request);
        $period = Carbon::now()->format('Y-m');

        $groups = $this->groups($request, $type);

        // This month's activity per category, so the list shows what each one is worth.
        $totals = Transaction::forUser($request->user()->id)
            ->ofType($type)
            ->inPeriod($period)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        // Flatten into table rows: a group row followed by its sub-categories.
        $rows = [];

        foreach ($groups as $group) {
            $childrenTotal = $group->children->sum(fn (Category $child) => (float) ($totals[$child->id] ?? 0));

            $rows[] = [
                'category' => $group,
                'is_group' => true,
                'parent' => null,
                'total' => (float) ($totals[$group->id] ?? 0) + $childrenTotal,
                'children' => $group->children->count(),
            ];

            foreach ($group->children as $child) {
                $rows[] = [
                    'category' => $child,
                    'is_group' => false,
                    'parent' => $group,
                    'total' => (float) ($totals[$child->id] ?? 0),
                    'children' => 0,
                ];
            }
        }

        return view('categories.index', [
            'type' => $type,
            'period' => $period,
            'rows' => $rows,
            'groups' => $groups,
            'groupCount' => $groups->count(),
            'leafCount' => $groups->sum(fn (Category $group) => $group->children->count()),
        ]);
    }

    public function create(Request $request): View
    {
        $type = $this->type($request);

        return view('categories.create', [
            'type' => $type,
            'category' => new Category(['type' => $type]),
            'groups' => $this->groups($request, $type),
        ]);
    }

    public function edit(Request $request, Category $category): View
    {
        $type = $this->type($request);
        $this->authorizeCategory($request, $category, $type);

        return view('categories.edit', [
            'type' => $type,
            'category' => $category,
            'groups' => $this->groups($request, $type)->reject(fn (Category $group) => $group->id === $category->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->type($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'parent_id' => ['nullable', 'integer'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $parentId = $data['parent_id'] ?? null;

        if ($parentId) {
            $parent = Category::forUser($request->user()->id)
                ->ofType($type)
                ->roots()
                ->whereKey($parentId)
                ->firstOrFail();

            if ($this->duplicateExists($request, $type, $parent->id, $data['name'])) {
                return back()->withInput()->with('error', "\"{$data['name']}\" already exists under {$parent->name}.");
            }

            Category::create([
                'user_id' => $request->user()->id,
                'parent_id' => $parent->id,
                'type' => $type,
                'name' => $data['name'],
                'color' => $parent->color,
                'sort_order' => (int) Category::where('parent_id', $parent->id)->max('sort_order') + 1,
            ]);

            return redirect()
                ->route("{$type}.categories", ["created" => 1])
                ->with('status', "Sub-category \"{$data['name']}\" added to {$parent->name}.");
        }

        if ($this->duplicateExists($request, $type, null, $data['name'])) {
            return back()->withInput()->with('error', "A group called \"{$data['name']}\" already exists.");
        }

        Category::create([
            'user_id' => $request->user()->id,
            'parent_id' => null,
            'type' => $type,
            'name' => $data['name'],
            'color' => ($data['color'] ?? null) ?: '#185fa5',
            'sort_order' => (int) Category::forUser($request->user()->id)->ofType($type)->roots()->max('sort_order') + 1,
        ]);

        return redirect()
            ->route("{$type}.categories", ["created" => 1])
            ->with('status', "Group \"{$data['name']}\" created.");
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $type = $this->type($request);
        $this->authorizeCategory($request, $category, $type);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'parent_id' => ['nullable', 'integer'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $isGroup = is_null($category->parent_id);

        // Groups always stay at the top level; only sub-categories can be moved.
        $parentId = $isGroup ? null : ($data['parent_id'] ?? $category->parent_id);

        if ($parentId && $parentId !== $category->parent_id) {
            Category::forUser($request->user()->id)
                ->ofType($type)
                ->roots()
                ->whereKey($parentId)
                ->firstOrFail();
        }

        if ($this->duplicateExists($request, $type, $parentId, $data['name'], $category->id)) {
            return back()->withInput()->with('error', "\"{$data['name']}\" is already used in this group.");
        }

        $category->update([
            'name' => $data['name'],
            'parent_id' => $parentId,
            'color' => $isGroup ? (($data['color'] ?? null) ?: $category->color) : $category->color,
        ]);

        return redirect()
            ->route("{$type}.categories")
            ->with('status', "\"{$data['name']}\" updated.");
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $type = $this->type($request);
        $this->authorizeCategory($request, $category, $type);

        $children = $category->children()->count();
        $name = $category->name;

        $category->delete();

        $message = $children > 0
            ? "Group \"{$name}\" and its {$children} sub-categories were deleted. Existing transactions were kept as uncategorised."
            : "\"{$name}\" was deleted. Existing transactions were kept as uncategorised.";

        return redirect()->route("{$type}.categories")->with('status', $message);
    }

    /**
     * Groups with their children, ordered for display.
     */
    private function groups(Request $request, string $type)
    {
        return Category::forUser($request->user()->id)
            ->ofType($type)
            ->roots()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    private function duplicateExists(Request $request, string $type, ?int $parentId, string $name, ?int $ignoreId = null): bool
    {
        return Category::forUser($request->user()->id)
            ->ofType($type)
            ->where('parent_id', $parentId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
    }

    private function authorizeCategory(Request $request, Category $category, string $type): void
    {
        abort_unless($category->user_id === $request->user()->id && $category->type === $type, 404);
    }

    private function type(Request $request): string
    {
        $type = (string) $request->route('type');

        abort_unless(in_array($type, ['income', 'expense'], true), 404);

        return $type;
    }
}
