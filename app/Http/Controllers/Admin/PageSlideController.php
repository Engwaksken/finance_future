<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PageSlideController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('media')) {
            $data['media_path'] = $request->file('media')->store('slides', 'public');
        }
        $slide = PageSlide::create($data);
        $request->attributes->set('audit_subject', $slide);

        return redirect()->route('admin.settings.index', ['tab' => 'slides'])->with('success', 'Page slide created.');
    }

    public function update(Request $request, PageSlide $pageSlide)
    {
        $data = $this->validated($request, $pageSlide);
        $oldPath = $pageSlide->media_path;
        if ($request->hasFile('media')) {
            $data['media_path'] = $request->file('media')->store('slides', 'public');
        } elseif ($data['background_type'] === 'color') {
            $data['media_path'] = null;
        }
        $pageSlide->update($data);
        if ($oldPath && $oldPath !== $pageSlide->media_path) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'slides'])->with('success', 'Page slide updated.');
    }

    public function destroy(PageSlide $pageSlide)
    {
        $path = $pageSlide->media_path;
        $pageSlide->delete();
        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'slides'])->with('success', 'Page slide deleted.');
    }

    private function validated(Request $request, ?PageSlide $slide = null): array
    {
        $type = $request->input('background_type');
        $needsMedia = in_array($type, ['image', 'video'], true)
            && (! $slide?->media_path || $slide->background_type !== $type);

        $data = $request->validate([
            'page' => ['required', Rule::in(array_keys(PageSlide::PAGES))],
            'category_id' => ['nullable', 'prohibited_unless:page,categories.show', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'background_type' => ['required', Rule::in(['image', 'color', 'video'])],
            'media' => [Rule::requiredIf($needsMedia), 'nullable', 'file',
                $type === 'video' ? 'mimes:mp4,webm' : 'mimes:jpeg,png,webp,gif',
                $type === 'video' ? 'max:20480' : 'max:4096',
                ...($type === 'image' ? ['image'] : []),
                Rule::prohibitedIf($type === 'color'),
            ],
            'background_color' => ['required', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'text_color' => ['required', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'button_label' => ['nullable', 'required_with:button_url', 'string', 'max:80'],
            'button_url' => ['nullable', 'required_with:button_label', 'string', 'max:500', 'regex:~^(?:https?://[^\s]+|/(?!/)[^\s\\\\]*)$~i'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        unset($data['media']);
        $data['category_id'] = $data['category_id'] ?? null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
