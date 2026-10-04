<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DatabaseBackupService
{
    public function create(): string
    {
        $dir = storage_path('app/backups');

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir.'/finance_future_'.now()->format('Ymd_His').'.sql';
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        $tables = collect($connection->select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0]);

        $sql = "-- Finance & Future Technology CMS backup\n";
        $sql .= "-- Database: {$database}\n";
        $sql .= "-- Generated: ".now()->toDateTimeString()."\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $create = $connection->select("SHOW CREATE TABLE `{$table}`");
            $createRow = (array) $create[0];
            $createSql = array_values($createRow)[1] ?? null;

            if (!$createSql) {
                continue;
            }

            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= $createSql.";\n\n";

            $rows = $connection->table($table)->get();

            foreach ($rows as $row) {
                $data = (array) $row;
                $columns = implode(', ', array_map(fn ($c) => "`{$c}`", array_keys($data)));
                $values = implode(', ', array_map(function ($value) use ($connection) {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return $connection->getPdo()->quote((string) $value);
                }, array_values($data)));

                $sql .= "INSERT INTO `{$table}` ({$columns}) VALUES ({$values});\n";
            }

            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        File::put($path, $sql);

        return $path;
    }

    public function prune(int $days): void
    {
        $dir = storage_path('app/backups');

        if (!File::isDirectory($dir)) {
            return;
        }

        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < now()->subDays($days)->timestamp) {
                File::delete($file->getPathname());
            }
        }
    }
}
