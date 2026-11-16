<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuilderTemplate;
use App\Services\PageBuilder\LayoutNormalizer;
use App\Services\PageBuilder\LayoutRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Endpoints used by the visual article builder.
 */
class BuilderController extends Controller
{
    private const MEDIA_DIRECTORIES = ['builder', 'articles'];

    /** Blank document the editor loads into its preview iframe. */
    public function canvas()
    {
        return response()->view('admin.builder.canvas')->header('X-Frame-Options', 'SAMEORIGIN');
    }

    /** Live render of an unsaved layout for the editor canvas. */
    public function render(Request $request): JsonResponse
    {
        $data = $request->validate([
            'layout' => ['required', 'string', 'max:'.ArticleController::MAX_LAYOUT_BYTES],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $layout = LayoutNormalizer::normalize(json_decode($data['layout'], true, 64));
        $rendered = LayoutRenderer::render($layout, ['editor' => true, 'title' => $data['title'] ?? '', 'url' => url('/')]);

        return response()->json($rendered + ['layout' => $layout]);
    }

    public function media(): JsonResponse
    {
        $disk = Storage::disk('public');
        $files = collect(self::MEDIA_DIRECTORIES)
            ->flatMap(fn ($dir) => $disk->exists($dir) ? $disk->files($dir) : [])
            ->filter(fn ($path) => preg_match('/\.(jpe?g|png|webp|gif|svg)$/i', $path))
            ->map(fn ($path) => ['path' => $path, 'url' => '/storage/'.$path, 'name' => basename($path), 'modified' => $disk->lastModified($path)])
            ->sortByDesc('modified')
            ->take(300)
            ->values();

        return response()->json($files);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file('file')->store('builder', 'public');

        return response()->json(['path' => $path, 'url' => '/storage/'.$path, 'name' => basename($path)], 201);
    }

    public function templates(): JsonResponse
    {
        return response()->json(BuilderTemplate::with('user:id,name')->latest()->get(['id', 'user_id', 'name', 'type', 'created_at'])
            ->map(fn (BuilderTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => $t->type,
                'author' => $t->user?->name,
                'created_human' => $t->created_at?->diffForHumans(),
            ]));
    }

    public function template(BuilderTemplate $template): JsonResponse
    {
        return response()->json(['id' => $template->id, 'name' => $template->name, 'type' => $template->type, 'layout' => $template->layout]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(BuilderTemplate::TYPES)],
            'layout' => ['required', 'string', 'max:'.ArticleController::MAX_LAYOUT_BYTES],
        ]);

        $layout = LayoutNormalizer::normalize(json_decode($data['layout'], true, 64));
        if ($layout['sections'] === []) {
            return response()->json(['message' => 'The template is empty.', 'errors' => ['layout' => ['The template is empty.']]], 422);
        }
        if ($data['type'] === 'section') {
            $layout['sections'] = array_slice($layout['sections'], 0, 1);
        }

        $template = BuilderTemplate::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'layout' => $layout,
        ]);

        return response()->json(['id' => $template->id, 'name' => $template->name, 'type' => $template->type, 'message' => 'Template saved.'], 201);
    }

    public function destroyTemplate(BuilderTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['message' => 'Template deleted.']);
    }
}
