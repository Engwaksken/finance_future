<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\HtmlSanitizer;
use App\Services\PageBuilder\PageBuilder;
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
            ->select(['id', 'title', 'slug', 'body', 'layout', 'layout_css'])
            ->orderBy('id')
            ->chunkById(200, function ($articles) use ($dryRun, &$scanned, &$changed, &$skipped) {
                foreach ($articles as $article) {
                    $scanned++;

                    try {
                        // Builder articles are re-rendered from their (re-validated) layout;
                        // sanitizing the rendered HTML would strip the builder's markup.
                        $built = $article->usesBuilder()
                            ? PageBuilder::build($article->layout, PageBuilder::context($article))
                            : null;
                        $clean = $built['html'] ?? HtmlSanitizer::clean($article->body);
                    } catch (\LengthException $exception) {
                        $skipped++;
                        $this->error("Skipped article #{$article->id}: {$exception->getMessage()}");

                        continue;
                    }

                    if ($clean === $article->body && ($built === null || $built['css'] === $article->layout_css)) {
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
                            ->update($built === null ? ['body' => $clean] : [
                                'body' => $clean,
                                'layout' => json_encode($built['layout']),
                                'layout_css' => $built['css'],
                            ]);
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
