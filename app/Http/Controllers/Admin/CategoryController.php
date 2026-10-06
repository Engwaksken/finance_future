<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PageSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('articles')->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:categories,name'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            ...$this->visualRules($request),
        ]);

        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active', true);
        $data = $this->storeVisual($request, $data);

        $category = Category::create($data);
        $request->attributes->set('audit_subject', $category);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            ...$this->visualRules($request, $category),
        ]);

        $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $oldImage = $category->featured_image;
        $data = $this->storeVisual($request, $data);

        $category->update($data);
        if ($request->hasFile('featured_image') && $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->articles()->exists()) {
            return back()->with('error', 'Move or remove articles before deleting this category.');
        }

        $image = $category->featured_image;
        $slideMedia = PageSlide::where('category_id', $category->id)->pluck('media_path')->filter()->all();
        $category->delete();
        Storage::disk('public')->delete(array_filter([$image, ...$slideMedia]));

        return back()->with('success', 'Category deleted.');
    }

    private function visualRules(Request $request, ?Category $category = null): array
    {
        return [
            'display_type' => ['sometimes', Rule::in(['image', 'icon', 'number'])],
            'featured_image' => [Rule::requiredIf($request->input('display_type') === 'image' && ! $category?->featured_image), 'nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:4096'],
            'icon' => ['required_if:display_type,icon', 'nullable', 'string', 'max:100', 'regex:/^(?:fas|far|fab|fa-solid|fa-regular|fa-brands) fa-[a-z0-9-]+$/'],
            'display_number' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    private function storeVisual(Request $request, array $data): array
    {
        unset($data['featured_image']);
        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('categories', 'public');
        }

        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
