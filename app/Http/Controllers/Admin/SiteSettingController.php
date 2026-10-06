<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PageSlide;
use App\Models\SiteSetting;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SiteSettingController extends Controller
{
    public function index()
    {
        $settings = SiteSetting::query()->pluck('value', 'key');
        $tabs = ['general' => 'General', 'branding' => 'Branding', 'backup' => 'Backups', 'maintenance' => 'Maintenance', 'slides' => 'Page Slides'];
        $tab = old('section', request('tab', 'general'));
        if (! is_string($tab) || ! array_key_exists($tab, $tabs)) {
            $tab = 'general';
        }
        $slides = $tab === 'slides' ? PageSlide::with('category')->orderBy('page')->orderBy('sort_order')->orderBy('id')->get() : collect();
        $categories = $tab === 'slides' ? Category::orderBy('name')->get() : collect();

        return view('admin.settings.index', compact('settings', 'tabs', 'tab', 'slides', 'categories'));
    }

    public function update(Request $request)
    {
        $request->validate(['section' => ['required', Rule::in(['general', 'branding', 'backup', 'maintenance'])]]);
        $section = $request->input('section');
        $rules = match ($section) {
            'general' => [
                'site_name' => ['required', 'string', 'max:150'],
                'site_tagline' => ['nullable', 'string', 'max:255'],
                'site_email' => ['nullable', 'email', 'max:255'],
            ],
            'branding' => [
                'site_logo' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:4096'],
                'site_favicon' => ['nullable', 'mimes:jpeg,png,webp,gif,ico', 'max:1024'],
                'remove_site_logo' => ['nullable', 'boolean'],
                'remove_site_favicon' => ['nullable', 'boolean'],
            ],
            'backup' => [
                'backup_enabled' => ['nullable', 'boolean'],
                'backup_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            ],
            'maintenance' => [
                'maintenance_enabled' => ['nullable', 'boolean'],
                'maintenance_message' => ['nullable', 'string', 'max:500'],
            ],
        };
        $data = $request->validate($rules);

        if ($section === 'branding') {
            foreach (['site_logo', 'site_favicon'] as $key) {
                $oldPath = SiteSetting::query()->where('key', $key)->value('value');
                if ($request->hasFile($key)) {
                    SiteSetting::put($key, $request->file($key)->store('branding', 'public'), 'branding');
                } elseif ($request->boolean('remove_'.$key)) {
                    SiteSetting::put($key, null, 'branding');
                } else {
                    continue;
                }
                if ($oldPath) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        } else {
            foreach (array_keys($rules) as $key) {
                $value = in_array($key, ['backup_enabled', 'maintenance_enabled'], true)
                    ? $request->boolean($key) : ($data[$key] ?? null);
                SiteSetting::put($key, $value, $section);
            }
        }

        return redirect()->route('admin.settings.index', ['tab' => $section])->with('success', 'Settings saved.');
    }

    public function backup(DatabaseBackupService $backup)
    {
        $path = $backup->create();
        $backup->prune((int) SiteSetting::valueOf('backup_retention_days', 14));

        return response()->download($path)->deleteFileAfterSend(true);
    }
}
