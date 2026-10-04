<?php

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('cms:backup')
    ->dailyAt('02:00')
    ->when(fn () => SiteSetting::valueOf('backup_enabled', '0') === '1');
