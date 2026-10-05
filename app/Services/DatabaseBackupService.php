<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseBackupService
{
    /**
     * Framework/ephemeral tables excluded from the data-only dump by default.
     *
     * These are recreated by `php artisan migrate` (migrations) or are
     * transient runtime state (cache, sessions, queue tables) that must never
     * be imported into a freshly migrated environment.
     *
     * @var list<string>
     */
    private const DEFAULT_EXCLUDED_TABLES = [
        'migrations',
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /**
     * @var list<string>
     */
    private array $excludedTables;

    /**
     * @param  list<string>|null  $excludedTables  Override the default exclusion list.
     */
    public function __construct(?array $excludedTables = null)
    {
        $this->excludedTables = $excludedTables ?? self::DEFAULT_EXCLUDED_TABLES;
    }

    /**
     * Create a portable, DATA-ONLY SQL backup of the configured database.
     *
     * This implementation uses Laravel's cross-driver schema introspection
     * (Schema::getTables() / Schema::getColumns()) and PDO::quote() for value
     * escaping, so it works on SQLite, MySQL, MariaDB and PostgreSQL without
     * any driver-specific raw SQL in the generic path.
     *
     * IMPORTANT (schema): the dump intentionally does NOT include the table
     * schema / DDL. Schema is versioned in `database/migrations`; to restore,
     * run `php artisan migrate` first, then import this file.
     *
     * IMPORTANT (security): backups are plaintext and, for the scheduled
     * backup path, are retained on disk until pruned by retention days.
     * Encryption-at-rest is NOT implemented here — it is recommended if
     * backups must be retained at rest.
     */
    public function create(): string
    {
        $dir = storage_path('app/backups');

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir.'/finance_future_'.now()->format('Ymd_His').'_'.Str::uuid().'.sql';

        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = $connection->getDatabaseName();
        $pdo = $connection->getPdo();
        $grammar = $connection->getQueryGrammar();

        $sql = $this->header($driver, $database);

        // Disable FK checks so tables can be imported in any order.
        $sql .= $this->disableForeignKeysSql($driver)."\n";

        foreach (Schema::getTables() as $table) {
            $tableName = $table['name'];

            // Skip framework/ephemeral tables (migrations, cache, sessions,
            // queue tables, ...) so restores don't import transient state.
            if (\in_array($tableName, $this->excludedTables, true)) {
                continue;
            }

            // Insertable columns only (skip generated/computed columns).
            $columns = $this->insertableColumns($tableName);

            if ($columns === []) {
                continue;
            }

            $quotedTable = $grammar->wrapTable($tableName);
            $quotedColumns = array_map(fn ($column) => $grammar->wrap($column), $columns);

            $sql .= "\n-- Table: {$tableName}\n";

            foreach ($connection->table($tableName)->get() as $row) {
                $values = [];

                foreach ($columns as $column) {
                    $values[] = $this->quoteValue($row->{$column} ?? null, $pdo);
                }

                $sql .= 'INSERT INTO '.$quotedTable
                    .' ('.implode(', ', $quotedColumns).')'
                    .' VALUES ('.implode(', ', $values).");\n";
            }
        }

        $sql .= "\n".$this->enableForeignKeysSql($driver)."\n";

        File::put($path, $sql);

        return $path;
    }

    /**
     * Delete backup files older than the given number of days.
     */
    public function prune(int $days): void
    {
        $dir = storage_path('app/backups');

        if (! File::isDirectory($dir)) {
            return;
        }

        foreach (File::files($dir) as $file) {
            if ($file->getMTime() < now()->subDays($days)->timestamp) {
                File::delete($file->getPathname());
            }
        }
    }

    /**
     * Column names that should be written to an INSERT statement.
     *
     * @return list<string>
     */
    private function insertableColumns(string $table): array
    {
        return collect(Schema::getColumns($table))
            ->reject(fn (array $column) => ! empty($column['generation']))
            ->pluck('name')
            ->all();
    }

    /**
     * Escape a single value for inclusion in a SQL dump, handling NULL.
     */
    private function quoteValue(mixed $value, \PDO $pdo): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        return $pdo->quote((string) $value);
    }

    private function header(string $driver, string $database): string
    {
        return "-- Finance & Future Technology CMS backup\n"
            ."-- Driver: {$driver}\n"
            ."-- Database: {$database}\n"
            .'-- Generated: '.now()->toDateTimeString()."\n"
            ."--\n"
            ."-- NOTE: This is a DATA-ONLY backup. Table schema is NOT included.\n"
            ."-- Recreate the schema first with `php artisan migrate`, then import this file.\n"
            ."-- Foreign key checks are disabled during import where the driver supports it.\n"
            ."\n";
    }

    private function disableForeignKeysSql(string $driver): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => 'SET FOREIGN_KEY_CHECKS=0;',
            'sqlite' => 'PRAGMA foreign_keys = OFF;',
            'pgsql' => 'SET session_replication_role = replica;',
            default => '',
        };
    }

    private function enableForeignKeysSql(string $driver): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => 'SET FOREIGN_KEY_CHECKS=1;',
            'sqlite' => 'PRAGMA foreign_keys = ON;',
            'pgsql' => 'SET session_replication_role = origin;',
            default => '',
        };
    }
}
