@extends('layouts.admin')
@section('title','Site Settings')
@section('heading','Site Settings')

@section('content')
<div class="panel">
<form method="POST" enctype="multipart/form-data" action="{{ route('admin.settings.update') }}">
@csrf @method('PUT')
<div class="panel-header"><h2 class="panel-title">Branding & General Settings</h2><button class="btn btn-primary">Save Settings</button></div>
<div class="panel-body">
    <div class="form-grid">
        <div class="field"><label>Site Name</label><input name="site_name" value="{{ old('site_name',$settings['site_name'] ?? 'Finance & Future Technology') }}" required></div>
        <div class="field"><label>Contact Email</label><input type="email" name="site_email" value="{{ old('site_email',$settings['site_email'] ?? '') }}"></div>
        <div class="field field-full"><label>Tagline</label><input name="site_tagline" value="{{ old('site_tagline',$settings['site_tagline'] ?? '') }}"></div>
        <div class="field"><label>Logo</label><input type="file" name="site_logo" accept="image/*"></div>
        <div class="field"><label>Favicon</label><input type="file" name="site_favicon" accept="image/*"></div>
    </div>
</div>

<div class="panel-header"><h2 class="panel-title">Backup Options</h2></div>
<div class="panel-body">
    <div class="form-grid">
        <div class="field"><label>Backup retention (days)</label><input type="number" min="1" max="365" name="backup_retention_days" value="{{ old('backup_retention_days',$settings['backup_retention_days'] ?? 14) }}"></div>
        <div class="field"><label class="checkbox-label"><input type="checkbox" name="backup_enabled" value="1" @checked(($settings['backup_enabled'] ?? '0') === '1')> Enable automatic daily backup</label></div>
    </div>
    @if(auth()->user()->hasPermission('backups.manage'))
    <a class="btn btn-light" href="{{ route('admin.settings.backup') }}"><i class="fa-solid fa-download"></i> Download Database Backup</a>
    @endif
</div>

<div class="panel-header"><h2 class="panel-title">Maintenance Options</h2></div>
<div class="panel-body">
    <div class="field"><label class="checkbox-label"><input type="checkbox" name="maintenance_enabled" value="1" @checked(($settings['maintenance_enabled'] ?? '0') === '1')> Enable maintenance mode</label></div>
    <div class="field"><label>Maintenance Message</label><textarea name="maintenance_message" rows="3">{{ old('maintenance_message',$settings['maintenance_message'] ?? 'We are carrying out scheduled maintenance. Please check back shortly.') }}</textarea></div>
</div>
</form>
</div>
@endsection
