@extends('layouts.admin')
@section('title', 'Site Settings')
@section('heading', 'Site Settings')

@section('content')
<div class="panel">
    <nav class="settings-tabs" aria-label="Settings sections">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.settings.index', ['tab' => $key]) }}" class="settings-tab {{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if($tab === 'slides')
        @include('admin.settings.partials.slides')
    @else
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.settings.update') }}">
            @csrf @method('PUT')
            <input type="hidden" name="section" value="{{ $tab }}">
            <div class="panel-header"><h2 class="panel-title">{{ $tabs[$tab] }} Settings</h2><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save {{ $tabs[$tab] }}</button></div>
            <div class="panel-body">
                @if($tab === 'general')
                    <div class="form-grid">
                        <div class="field"><label for="site_name">Site Name</label><input id="site_name" name="site_name" value="{{ old('site_name', $settings['site_name'] ?? 'Finance & Future Technology') }}" maxlength="150" required></div>
                        <div class="field"><label for="site_email">Contact Email</label><input id="site_email" type="email" name="site_email" value="{{ old('site_email', $settings['site_email'] ?? '') }}"></div>
                        <div class="field field-full"><label for="site_tagline">Tagline</label><input id="site_tagline" name="site_tagline" value="{{ old('site_tagline', $settings['site_tagline'] ?? '') }}" maxlength="255"></div>
                    </div>
                @elseif($tab === 'branding')
                    <div class="form-grid">
                        @foreach(['site_logo' => 'Logo', 'site_favicon' => 'Favicon'] as $key => $label)
                            <div class="field">
                                <label for="{{ $key }}">{{ $label }}</label>
                                @if(!empty($settings[$key]))
                                    <img class="branding-preview" src="{{ asset('storage/'.$settings[$key]) }}" alt="Current {{ strtolower($label) }}">
                                    <label class="checkbox-label"><input type="checkbox" name="remove_{{ $key }}" value="1" @checked(old('remove_'.$key))> Remove current {{ strtolower($label) }}</label>
                                @endif
                                <input id="{{ $key }}" type="file" name="{{ $key }}" accept="{{ $key === 'site_logo' ? 'image/jpeg,image/png,image/webp,image/gif' : 'image/jpeg,image/png,image/webp,image/gif,image/x-icon,.ico' }}">
                                <small class="field-help">{{ $key === 'site_logo' ? 'JPG, PNG, WebP or GIF. Maximum 4 MB.' : 'JPG, PNG, WebP, GIF or ICO. Maximum 1 MB.' }}</small>
                            </div>
                        @endforeach
                    </div>
                @elseif($tab === 'backup')
                    <div class="form-grid">
                        <div class="field"><label for="backup_retention_days">Backup retention (days)</label><input id="backup_retention_days" type="number" min="1" max="365" name="backup_retention_days" value="{{ old('backup_retention_days', $settings['backup_retention_days'] ?? 14) }}" required></div>
                        <div class="field"><input type="hidden" name="backup_enabled" value="0"><label class="checkbox-label"><input type="checkbox" name="backup_enabled" value="1" @checked(old('backup_enabled', $settings['backup_enabled'] ?? '0') == '1')> Enable automatic daily backup</label><small class="field-help">Automatic backups require the Laravel scheduler to be running.</small></div>
                    </div>
                    @if(auth()->user()->hasPermission('backups.manage'))
                        <a class="btn btn-light" href="{{ route('admin.settings.backup') }}"><i class="fa-solid fa-download"></i> Download Database Backup</a>
                    @endif
                @elseif($tab === 'maintenance')
                    <div class="field"><input type="hidden" name="maintenance_enabled" value="0"><label class="checkbox-label"><input type="checkbox" name="maintenance_enabled" value="1" @checked(old('maintenance_enabled', $settings['maintenance_enabled'] ?? '0') == '1')> Enable maintenance mode</label></div>
                    <div class="field"><label for="maintenance_message">Maintenance Message</label><textarea id="maintenance_message" name="maintenance_message" rows="3" maxlength="500">{{ old('maintenance_message', $settings['maintenance_message'] ?? 'We are carrying out scheduled maintenance. Please check back shortly.') }}</textarea></div>
                @endif
            </div>
        </form>
    @endif
</div>
@endsection
