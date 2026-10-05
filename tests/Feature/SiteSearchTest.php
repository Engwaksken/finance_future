<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_public_articles_events_and_topics_without_exposing_drafts(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Digital Banking', 'slug' => 'digital-banking', 'is_active' => true]);
        foreach (['published', 'draft'] as $status) {
            Article::create([
                'author_id' => $user->id, 'title' => 'Digital '.$status.' article',
                'slug' => 'digital-'.$status, 'body' => 'Coverage', 'status' => $status,
                'published_at' => now()->subDay(), 'category_id' => $category->id,
            ]);
            Event::create([
                'created_by' => $user->id, 'title' => 'Digital '.$status.' event',
                'slug' => 'digital-'.$status, 'starts_at' => now()->addDay(), 'status' => $status,
            ]);
        }

        $this->get(route('search', ['q' => 'Digital']))->assertOk()
            ->assertSee('Digital published article')->assertSee('Digital published event')
            ->assertSee('Digital Banking')->assertDontSee('Digital draft article')->assertDontSee('Digital draft event');
        $this->get(route('events.show', 'digital-published'))->assertOk();
        $this->get(route('events.show', 'digital-draft'))->assertNotFound();
    }

    public function test_search_finds_site_pages_and_handles_empty_or_invalid_queries(): void
    {
        $this->get(route('search', ['q' => 'privacy']))->assertOk()->assertSee(route('privacy'));
        $this->get(route('search'))->assertOk()->assertSee('Use the search bar');
        $this->get(route('search', ['q' => 'nothing-matches-this-query']))->assertOk()->assertSee('Nothing matched');
        $this->get(route('search', ['q' => ['invalid']]))->assertSessionHasErrors('q');
    }

    public function test_original_navigation_and_footer_links_work_without_seeded_categories(): void
    {
        $this->get('/')->assertOk()->assertSee('Policy &amp; Regulation', false)
            ->assertSee('FinTech')->assertSee('Future Tech')->assertSee('Insights')
            ->assertSee('Interviews')->assertSee('Media')->assertSee(route('contact'))
            ->assertSee(route('privacy'))->assertSee(route('search'));

        foreach (['contact', 'privacy', 'events.index', 'about'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('articles.index', ['category' => 'finance']))->assertOk()->assertSee('No published articles');
    }

    public function test_category_links_filter_articles_and_inactive_topics_are_not_in_navigation(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Finance', 'slug' => 'finance', 'is_active' => true]);
        Category::create(['name' => 'Hidden topic', 'slug' => 'hidden-topic', 'is_active' => false]);
        foreach (['Finance coverage' => $category->id, 'Other coverage' => null] as $title => $categoryId) {
            Article::create([
                'author_id' => $user->id, 'title' => $title, 'slug' => str_replace(' ', '-', $title),
                'body' => 'Coverage', 'category_id' => $categoryId, 'status' => 'published', 'published_at' => now()->subDay(),
            ]);
        }

        $this->get(route('articles.index', ['category' => 'finance']))->assertOk()
            ->assertSee('Finance coverage')->assertDontSee('Other coverage')->assertDontSee('Hidden topic');
        $this->get(route('search', ['q' => 'Finance']))->assertOk()->assertSee('Finance coverage')->assertDontSee('Other coverage');
    }
}
