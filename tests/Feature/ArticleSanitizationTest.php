<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_create_and_update_remove_unsafe_html_and_preserve_content(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $body = '<h2>Markets</h2><p onclick="alert(1)">News <strong>today</strong></p><script>alert(1)</script><a href="javascript:alert(1)">Link</a>';
        $data = ['title' => 'Market news', 'body' => $body, 'status' => 'draft'];

        $this->actingAs($user)->post(route('admin.articles.store'), $data)->assertSessionHasNoErrors();
        $article = Article::firstOrFail();
        $this->assertStringContainsString('<strong>today</strong>', $article->body);
        $this->assertStringNotContainsString('<script', $article->body);
        $this->assertStringNotContainsString('onclick', $article->body);
        $this->assertStringNotContainsString('javascript:', $article->body);

        $data['body'] = '<p>Updated</p><img src="/storage/photo.png" onerror="alert(1)">';
        $this->put(route('admin.articles.update', $article), $data)->assertSessionHasNoErrors();
        $this->assertStringContainsString('/storage/photo.png', $article->fresh()->body);
        $this->assertStringNotContainsString('onerror', $article->fresh()->body);
    }

    public function test_oversized_article_is_rejected_instead_of_truncated(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($user)->post(route('admin.articles.store'), [
            'title' => 'Long article',
            'body' => str_repeat('x', HtmlSanitizer::MAX_INPUT_LENGTH + 1),
            'status' => 'draft',
        ])->assertSessionHasErrors('body');

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_resanitization_supports_dry_run_and_preserves_deleted_rows_and_timestamps(): void
    {
        $user = User::factory()->create();
        $article = Article::create([
            'author_id' => $user->id, 'title' => 'Old content', 'slug' => 'old-content',
            'body' => '<p onclick="alert(1)">Keep this</p>',
        ]);
        $article->delete();
        $updatedAt = $article->fresh()->getRawOriginal('updated_at');

        $this->artisan('articles:resanitize', ['--dry-run' => true])->assertSuccessful();
        $this->assertStringContainsString('onclick', $article->fresh()->body);
        $this->artisan('articles:resanitize')->assertSuccessful();
        $this->assertSame('<p>Keep this</p>', $article->fresh()->body);
        $this->assertSame($updatedAt, $article->fresh()->getRawOriginal('updated_at'));
        $this->assertTrue($article->fresh()->trashed());
    }

    public function test_resanitization_skips_oversized_content_without_data_loss(): void
    {
        $article = Article::create([
            'author_id' => User::factory()->create()->id, 'title' => 'Oversized', 'slug' => 'oversized',
            'body' => str_repeat('x', HtmlSanitizer::MAX_INPUT_LENGTH + 1),
        ]);

        $this->artisan('articles:resanitize')->assertFailed();
        $this->assertSame($article->body, $article->fresh()->body);
    }
}
