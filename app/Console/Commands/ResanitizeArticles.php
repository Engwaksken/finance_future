<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\HtmlSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResanitizeArticles extends Command
{
    protected $signature = 'articles:resanitize {--dry-run : Report which articles would change without writing}';

    protected $description = 'Re-sanitize stored article bodies to remove unsafe HTML';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $scanned = 0;
        $changed = 0;
        $skipped = 0;

        Article::withTrashed()
            ->select(['id', 'title', 'body'])
            ->orderBy('id')
            ->chunkById(200, function ($articles) use ($dryRun, &$scanned, &$changed, &$skipped) {
                foreach ($articles as $article) {
                    $scanned++;

                    try {
                        $clean = HtmlSanitizer::clean($article->body);
                    } catch (\LengthException $exception) {
                        $skipped++;
                        $this->error("Skipped article #{$article->id}: {$exception->getMessage()}");

                        continue;
                    }

                    if ($clean === $article->body) {
                        continue;
                    }

                    $changed++;

                    if ($dryRun) {
                        $this->line("Would sanitize article #{$article->id}: {$article->title}");
                    } else {
                        // Use the base query builder so updated_at is not bumped
                        // (Eloquent would touch it) and soft-deleted rows are
                        // included (the query builder has no soft-delete scope).
                        DB::table('articles')
                            ->where('id', $article->id)
                            ->update(['body' => $clean]);
                    }
                }
            });

        $this->info(sprintf(
            '%sScanned %d article(s), %d required sanitization.',
            $dryRun ? '[DRY RUN] ' : '',
            $scanned,
            $changed
        ));

        return $skipped > 0 ? self::FAILURE : self::SUCCESS;
    }
}
