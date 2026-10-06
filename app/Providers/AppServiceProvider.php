<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\PageSlide;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('partials.page-slides', function ($view) {
            $page = request()->route()?->getName();
            $slides = collect();
            if (array_key_exists($page ?? '', PageSlide::PAGES)) {
                $categoryId = $page === 'categories.show'
                    ? Category::where('slug', request()->route('slug'))->value('id') : null;
                $slides = PageSlide::where('page', $page)->where('is_active', true)
                    ->where(function ($query) use ($categoryId) {
                        $query->whereNull('category_id');
                        if ($categoryId) {
                            $query->orWhere('category_id', $categoryId);
                        }
                    })->orderBy('sort_order')->orderBy('id')->get();
            }
            $view->with('pageSlides', $slides);
        });

        View::composer(['partials.site-header', 'partials.site-footer'], function ($view) {
            $categories = Category::orderBy('sort_order')->get()->keyBy('slug');
            $topics = collect(config('site.topics'))->map(function ($label, $slug) use ($categories) {
                $category = $categories->get($slug);

                if ($category && ! $category->is_active) {
                    return null;
                }

                return [
                    'label' => $category?->name ?? $label,
                    'slug' => $slug,
                    'url' => $category
                        ? route('categories.show', $slug)
                        : route('articles.index', ['category' => $slug]),
                ];
            })->filter();

            foreach ($categories as $slug => $category) {
                if ($category->is_active && $slug !== 'events' && ! $topics->has($slug)
                    && ! array_key_exists($slug, config('site.topics'))) {
                    $topics->put($slug, [
                        'label' => $category->name,
                        'slug' => $slug,
                        'url' => route('categories.show', $slug),
                    ]);
                }
            }

            $view->with('siteTopics', $topics);
        });
    }
}
