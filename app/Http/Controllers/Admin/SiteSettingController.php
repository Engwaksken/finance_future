<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiteSettingController extends Controller
{
    public function index()
    {
        $settings = SiteSetting::query()->pluck('value', 'key');

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:150'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'site_email' => ['nullable', 'email', 'max:255'],
            'site_logo' => ['nullable', 'mimes:jpeg,png,webp,gif', 'max:4096'],
            'site_favicon' => ['nullable', 'mimes:jpeg,png,webp,gif,ico', 'max:1024'],
            'backup_enabled' => ['nullable', 'boolean'],
            'backup_retention_days' => ['required', 'integer', 'min:1', 'max:365'],
            'maintenance_enabled' => ['nullable', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
        ]);

        foreach (['site_name', 'site_tagline', 'site_email'] as $key) {
            SiteSetting::put($key, $data[$key] ?? null, 'general');
        }

        if ($request->hasFile('site_logo')) {
            $path = $request->file('site_logo')->store('branding', 'public');
            SiteSetting::put('site_logo', $path, 'branding');
        }

        if ($request->hasFile('site_favicon')) {
            $path = $request->file('site_favicon')->store('branding', 'public');
            SiteSetting::put('site_favicon', $path, 'branding');
        }

        SiteSetting::put('backup_enabled', $request->boolean('backup_enabled'), 'backup');
        SiteSetting::put('backup_retention_days', $data['backup_retention_days'], 'backup');
        SiteSetting::put('maintenance_enabled', $request->boolean('maintenance_enabled'), 'maintenance');
        SiteSetting::put('maintenance_message', $data['maintenance_message'] ?? null, 'maintenance');

        return back()->with('success', 'Site settings updated.');
    }

    public function backup(DatabaseBackupService $backup)
    {
        $path = $backup->create();
        $backup->prune((int) SiteSetting::valueOf('backup_retention_days', 14));

        return response()->download($path)->deleteFileAfterSend(true);
    }
}
