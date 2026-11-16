<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\BuilderTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PageBuilder\LayoutNormalizer;
use App\Services\PageBuilder\PageBuilder;
use App\Services\PageBuilder\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArticleBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    }

    private function layout(array $widgets, array $sectionSettings = []): string
    {
        return json_encode(['sections' => [[
            'id' => 'sec00001',
            'settings' => $sectionSettings,
            'columns' => [['id' => 'col00001', 'settings' => [], 'widgets' => $widgets]],
        ]]]);
    }

    public function test_builder_pages_load_for_new_and_classic_articles(): void
    {
        $this->actingAs($this->admin)->get(route('admin.articles.create'))
            ->assertOk()->assertSee('id="ffb-config"', false)->assertSee('ffbe-frame', false);

        $article = Article::create([
            'author_id' => $this->admin->id, 'title' => 'Classic', 'slug' => 'classic',
            'body' => '<p>Old <strong>content</strong></p>', 'status' => 'draft',
        ]);

        $response = $this->get(route('admin.articles.edit', $article))->assertOk();
        $config = json_decode($this->configFrom($response->getContent()), true);
        $this->assertSame('text', $config['layout']['sections'][0]['columns'][0]['widgets'][0]['type']);
        $this->assertStringContainsString('<strong>content</strong>', $config['layout']['sections'][0]['columns'][0]['widgets'][0]['settings']['content']);

        $this->get(route('admin.builder.canvas'))->assertOk()->assertSee('id="ffb-root"', false);
    }

    public function test_storing_a_builder_layout_renders_safe_html_css_and_a_revision(): void
    {
        $layout = $this->layout([
            ['id' => 'wid00001', 'type' => 'heading', 'settings' => ['text' => '<script>alert(1)</script>Rates', 'tag' => 'h2', 'color' => 'red;}body{display:none', 'font_size' => ['desktop' => 40, 'mobile' => 24]]],
            ['id' => 'wid00002', 'type' => 'text', 'settings' => ['content' => '<p onclick="x()">Hello <a href="javascript:alert(1)">bad</a> <b>bold</b></p><script>x</script>']],
            ['id' => 'wid00003', 'type' => 'button', 'settings' => ['text' => 'Go', 'link' => 'javascript:alert(1)']],
            ['id' => 'wid00004', 'type' => 'image', 'settings' => ['src' => 'javascript:alert(1)']],
            ['id' => 'wid00005', 'type' => 'not-a-widget', 'settings' => []],
            ['id' => 'wid00006', 'type' => 'video', 'settings' => ['url' => 'https://youtu.be/dQw4w9WgXcQ']],
        ], ['bg_type' => 'classic', 'bg_image' => 'https://x.test/a.png");}body{x:url("', 'bg_color' => '#0b3b60']);

        $response = $this->actingAs($this->admin)->postJson(route('admin.articles.store'), [
            'title' => 'Builder article',
            'status' => 'published',
            'layout' => $layout,
            'page_template' => 'wide',
            'new_tags' => 'Fintech, AI',
        ])->assertOk()->assertJsonPath('article.slug', 'builder-article');

        $article = Article::with('tags')->findOrFail($response->json('article.id'));
        $this->assertTrue($article->usesBuilder());
        $this->assertSame('wide', $article->page_template);
        $this->assertEqualsCanonicalizing(['Fintech', 'AI'], $article->tags->pluck('name')->all());
        $this->assertNotNull($article->published_at);

        // HTML: text escaped, rich text sanitized, unsafe URLs and unknown widgets dropped.
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;Rates', $article->body);
        $this->assertStringContainsString('<b>bold</b>', $article->body);
        $this->assertStringNotContainsString('<script', $article->body);
        $this->assertStringNotContainsString('onclick', $article->body);
        $this->assertStringNotContainsString('javascript:', $article->body);
        $this->assertStringContainsString('href="#"', $article->body);
        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $article->body);
        $this->assertStringNotContainsString('not-a-widget', $article->body);
        $this->assertCount(5, $article->layout['sections'][0]['columns'][0]['widgets']);

        // CSS: only validated values reach the stylesheet.
        $this->assertStringContainsString('.ffb .ffb-e-wid00001 .ffb-heading{font-size:40px}', $article->layout_css);
        $this->assertStringContainsString('@media (max-width:767px)', $article->layout_css);
        $this->assertStringContainsString('background-color:#0b3b60', $article->layout_css);
        $this->assertStringNotContainsString('display:none', $article->layout_css);
        $this->assertStringNotContainsString('url(', $article->layout_css);

        $this->assertSame(1, $article->revisions()->count());

        $this->get(route('articles.show', $article->slug))
            ->assertOk()
            ->assertSee('class="ffb"', false)
            ->assertSee('.ffb .ffb-e-wid00001 .ffb-heading{font-size:40px}', false)
            ->assertSee('article-wrap--wide', false)
            ->assertSee('#Fintech');
    }

    public function test_updates_keep_revisions_that_can_be_loaded_back(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.articles.store'), [
            'title' => 'Versioned', 'status' => 'draft',
            'layout' => $this->layout([['id' => 'wid00001', 'type' => 'heading', 'settings' => ['text' => 'First']]]),
        ])->assertOk();
        $article = Article::firstOrFail();

        $this->putJson(route('admin.articles.update', $article), [
            'title' => 'Versioned', 'status' => 'draft', 'slug' => 'custom permalink',
            'layout' => $this->layout([['id' => 'wid00001', 'type' => 'heading', 'settings' => ['text' => 'Second']]]),
        ])->assertOk()->assertJsonPath('article.slug', 'custom-permalink');

        $revisions = $this->getJson(route('admin.articles.revisions', $article))->assertOk()->json();
        $this->assertCount(2, $revisions);

        $first = $revisions[1]['id'];
        $this->getJson(route('admin.articles.revisions.show', [$article, $first]))
            ->assertOk()
            ->assertJsonPath('layout.sections.0.columns.0.widgets.0.settings.text', 'First');

        $other = Article::create(['author_id' => $this->admin->id, 'title' => 'Other', 'slug' => 'other', 'body' => '<p>x</p>']);
        $this->getJson(route('admin.articles.revisions.show', [$other, $first]))->assertNotFound();
    }

    public function test_live_render_endpoint_returns_editor_markup(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.builder.render'), [
            'layout' => $this->layout([['id' => 'wid00001', 'type' => 'image', 'settings' => []]]),
        ])->assertOk()
            ->assertJsonPath('layout.sections.0.id', 'sec00001')
            ->assertSee('data-ffb-id=\"wid00001\"', false)
            ->assertSee('ffb-widget-placeholder', false);
    }

    public function test_templates_can_be_saved_listed_loaded_and_deleted(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.builder.templates.store'), [
            'name' => 'Intro', 'type' => 'section',
            'layout' => json_encode(['sections' => [
                ['columns' => [['widgets' => [['type' => 'heading']]]]],
                ['columns' => [['widgets' => [['type' => 'text']]]]],
            ]]),
        ])->assertCreated();

        $template = BuilderTemplate::firstOrFail();
        $this->assertCount(1, $template->layout['sections']);
        $this->getJson(route('admin.builder.templates.index'))->assertOk()->assertJsonPath('0.name', 'Intro');
        $this->getJson(route('admin.builder.templates.show', $template))->assertOk()->assertJsonPath('layout.sections.0.columns.0.widgets.0.type', 'heading');
        $this->deleteJson(route('admin.builder.templates.destroy', $template))->assertOk();
        $this->assertDatabaseCount('builder_templates', 0);

        $this->postJson(route('admin.builder.templates.store'), ['name' => 'Empty', 'type' => 'page', 'layout' => '{"sections":[]}'])->assertStatus(422);
    }

    public function test_media_library_uploads_and_lists_images_only(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('admin.builder.media.store'), [
            'file' => UploadedFile::fake()->image('chart.png'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonStructure(['url', 'path']);

        $this->post(route('admin.builder.media.store'), [
            'file' => UploadedFile::fake()->create('evil.php', 1, 'application/x-php'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $files = $this->getJson(route('admin.builder.media.index'))->assertOk()->json();
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('/storage/builder/', $files[0]['url']);
    }

    public function test_duplicate_bulk_actions_trash_and_restore(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.articles.store'), [
            'title' => 'Original', 'status' => 'published',
            'layout' => $this->layout([['type' => 'heading', 'settings' => ['text' => 'Hi']]]),
        ])->assertOk();
        $original = Article::firstOrFail();

        $this->post(route('admin.articles.duplicate', $original))->assertRedirect();
        $copy = Article::where('id', '!=', $original->id)->firstOrFail();
        $this->assertSame('Original (Copy)', $copy->title);
        $this->assertSame('draft', $copy->status);
        $this->assertTrue($copy->usesBuilder());

        $this->post(route('admin.articles.bulk'), ['action' => 'publish', 'ids' => [$copy->id]])->assertSessionHas('success');
        $this->assertSame('published', $copy->fresh()->status);

        $this->post(route('admin.articles.bulk'), ['action' => 'trash', 'ids' => [$original->id, $copy->id]]);
        $this->assertSoftDeleted($original);
        $this->get(route('admin.articles.index', ['view' => 'trash']))->assertOk()->assertSee('Original (Copy)');

        $this->patch(route('admin.articles.restore', $original->id))->assertSessionHas('success');
        $this->assertNotSoftDeleted($original);

        $this->delete(route('admin.articles.force-destroy', $copy->id))->assertSessionHas('success');
        $this->assertDatabaseMissing('articles', ['id' => $copy->id]);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => (string) $copy->id, 'action' => 'deleted permanently']);
    }

    public function test_preview_renders_unsaved_content_without_saving(): void
    {
        $this->actingAs($this->admin)->post(route('admin.articles.preview'), [
            'title' => 'Draft preview',
            'layout' => $this->layout([['type' => 'heading', 'settings' => ['text' => 'Preview heading']]]),
        ])->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Preview heading')
            ->assertSee('not saved or published');

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_builder_endpoints_require_article_permission(): void
    {
        $permission = Permission::create(['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'group' => 'system']);
        $role = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['role' => 'reader', 'status' => 'active']);
        $user->roles()->attach($role);

        $this->actingAs($user);
        $this->get(route('admin.articles.create'))->assertForbidden();
        $this->postJson(route('admin.builder.render'), ['layout' => '{}'])->assertForbidden();
        $this->getJson(route('admin.builder.media.index'))->assertForbidden();
        $this->postJson(route('admin.builder.templates.store'), [])->assertForbidden();
    }

    public function test_resanitize_rerenders_builder_articles_instead_of_stripping_them(): void
    {
        $built = PageBuilder::build(json_decode($this->layout([['id' => 'wid00001', 'type' => 'button', 'settings' => ['text' => 'Go']]]), true));
        $article = Article::create([
            'author_id' => $this->admin->id, 'title' => 'Built', 'slug' => 'built',
            'body' => $built['html'].'<script>tampered</script>', 'layout' => $built['layout'], 'layout_css' => $built['css'],
        ]);

        $this->artisan('articles:resanitize')->assertSuccessful();
        $body = $article->fresh()->body;
        $this->assertStringNotContainsString('tampered', $body);
        $this->assertStringContainsString('class="ffb-btn ffb-btn--filled"', $body);
    }

    public function test_every_widget_type_renders_with_its_defaults(): void
    {
        $widgets = array_map(fn ($type) => ['type' => $type], array_keys(Schema::all()['widgets']));
        $layout = LayoutNormalizer::normalize(['sections' => [['columns' => [['widgets' => $widgets]]]]]);
        $this->assertCount(count($widgets), $layout['sections'][0]['columns'][0]['widgets']);

        foreach ([false, true] as $editor) {
            $result = PageBuilder::build($layout, ['editor' => $editor, 'url' => 'https://example.test/a', 'title' => 'T']);
            $this->assertStringStartsWith('<div class="ffb', $result['html']);
            $this->assertStringNotContainsString('{{', $result['css']);
        }
    }

    private function configFrom(string $html): string
    {
        preg_match('~<script type="application/json" id="ffb-config">(.*?)</script>~s', $html, $m);

        return $m[1] ?? '';
    }
}
