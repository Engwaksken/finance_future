<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class CmsBackup extends Command
{
    protected $signature = 'cms:backup';
    protected $description = 'Create a Finance & Future Technology CMS database backup';

    public function handle(DatabaseBackupService $backup): int
    {
        $path = $backup->create();
        $days = max(1, (int) SiteSetting::valueOf('backup_retention_days', 14));
        $backup->prune($days);

        $this->info("Backup created: {$path}");

        return self::SUCCESS;
    }
}
