@php
    $selectedTags = $article->exists ? $article->tags->pluck('id')->all() : [];
    $config = [
        'schema' => $schema,
        'layout' => $article->layout,
        'siteUrl' => url('/'),
        'openSettings' => ! $article->exists,
        'article' => [
            'id' => $article->id,
            'slug' => $article->slug,
            'status' => $article->status ?: 'draft',
            'edit_url' => $article->exists ? route('admin.articles.edit', $article) : null,
            'update_url' => $article->exists ? route('admin.articles.update', $article) : null,
            'revisions_url' => $article->exists ? route('admin.articles.revisions', $article) : null,
            'public_url' => $article->exists ? route('articles.show', $article->slug) : null,
            'updated_ts' => $article->updated_at?->timestamp,
        ],
        'urls' => [
            'store' => route('admin.articles.store'),
            'render' => route('admin.builder.render'),
            'media' => route('admin.builder.media.index'),
            'upload' => route('admin.builder.media.store'),
            'templates' => route('admin.builder.templates.index'),
        ],
    ];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $article->exists ? $article->title : 'New article' }} | Builder</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230b3b60'/%3E%3Ctext x='16' y='22' font-family='Arial,sans-serif' font-size='16' font-weight='700' fill='%23b99146' text-anchor='middle'%3EF%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    @vite(['resources/css/builder.css', 'resources/js/builder/editor.js'])
</head>
<body>
<div class="ffbe" id="ffbe">
    <header class="ffbe-topbar">
        <div class="ffbe-topbar__group">
            <a class="ffbe-icon-btn ffbe-back" href="{{ route('admin.articles.index') }}" title="Back to articles" aria-label="Back to articles"><i class="fa-solid fa-arrow-left"></i></a>
            <span class="ffbe-brand"><i class="fa-solid fa-layer-group"></i> Builder</span>
            <nav class="ffbe-viewnav" aria-label="Builder panels">
                <button type="button" class="ffbe-icon-btn" data-view-btn="widgets" title="Add elements" aria-label="Add elements"><i class="fa-solid fa-plus"></i></button>
                <button type="button" class="ffbe-icon-btn" data-view-btn="page" title="Article settings" aria-label="Article settings"><i class="fa-solid fa-sliders"></i></button>
                <button type="button" class="ffbe-icon-btn" data-action="navigator" title="Navigator (Ctrl+I)" aria-label="Navigator"><i class="fa-solid fa-layer-group"></i></button>
                <button type="button" class="ffbe-icon-btn" data-view-btn="history" title="History and revisions" aria-label="History and revisions"><i class="fa-solid fa-clock-rotate-left"></i></button>
                <button type="button" class="ffbe-icon-btn" data-action="templates" title="Templates library" aria-label="Templates library"><i class="fa-solid fa-folder-open"></i></button>
            </nav>
        </div>

        <div class="ffbe-topbar__center">
            <span class="ffbe-top-title" id="ffbe-top-title">{{ $article->title ?: 'Untitled article' }}</span>
            <span class="ffbe-status" id="ffbe-top-status" data-status="{{ $article->exists ? $article->status : 'new' }}">{{ $article->exists ? $article->status : 'new' }}</span>
            <div class="ffbe-devices" role="group" aria-label="Responsive mode">
                <button type="button" class="ffbe-icon-btn is-active" data-device="desktop" aria-pressed="true" title="Desktop" aria-label="Desktop preview"><i class="fa-solid fa-desktop"></i></button>
                <button type="button" class="ffbe-icon-btn" data-device="tablet" aria-pressed="false" title="Tablet (768px)" aria-label="Tablet preview"><i class="fa-solid fa-tablet-screen-button"></i></button>
                <button type="button" class="ffbe-icon-btn" data-device="mobile" aria-pressed="false" title="Mobile (375px)" aria-label="Mobile preview"><i class="fa-solid fa-mobile-screen-button"></i></button>
            </div>
        </div>

        <div class="ffbe-topbar__group ffbe-topbar__group--end">
            <button type="button" class="ffbe-icon-btn" data-action="undo" aria-label="Undo" disabled><i class="fa-solid fa-rotate-left"></i></button>
            <button type="button" class="ffbe-icon-btn" data-action="redo" aria-label="Redo" disabled><i class="fa-solid fa-rotate-right"></i></button>
            <button type="button" class="ffbe-icon-btn" data-action="shortcuts" title="Keyboard shortcuts (?)" aria-label="Keyboard shortcuts"><i class="fa-regular fa-keyboard"></i></button>
            <a class="ffbe-icon-btn" id="ffbe-view-live" href="{{ $config['article']['public_url'] ?? '#' }}" target="_blank" rel="noopener" title="View published article" aria-label="View published article" @if($article->status !== 'published') hidden @endif><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            <button type="button" class="ffbe-btn ffbe-btn--ghost" data-action="preview" title="Preview unsaved changes (Ctrl+P)"><i class="fa-solid fa-eye"></i> <span class="ffbe-hide-sm">Preview</span></button>
            <span class="ffbe-save-state" id="ffbe-save-state" data-state="{{ $article->exists ? 'saved' : 'dirty' }}" aria-live="polite">{{ $article->exists ? 'All changes saved' : 'Not saved yet' }}</span>
            <div class="ffbe-split">
                <button type="button" class="ffbe-btn ffbe-btn--primary ffbe-split__main" id="ffbe-publish"><i class="fa-solid fa-paper-plane"></i> <span>Publish</span></button>
                <button type="button" class="ffbe-btn ffbe-btn--primary ffbe-split__toggle" id="ffbe-publish-more" aria-haspopup="menu" aria-expanded="false" aria-label="More save options"><i class="fa-solid fa-caret-down"></i></button>
                <div class="ffbe-menu ffbe-split__menu" id="ffbe-publish-menu" role="menu" hidden>
                    <button type="button" class="ffbe-menu__item" role="menuitem" data-action="save-draft"><i class="fa-solid fa-file-pen"></i><span>Save as draft</span></button>
                    <button type="button" class="ffbe-menu__item" role="menuitem" data-action="save-review"><i class="fa-solid fa-user-check"></i><span>Submit for review</span></button>
                    <button type="button" class="ffbe-menu__item" role="menuitem" data-action="save-schedule"><i class="fa-solid fa-calendar-days"></i><span>Schedule…</span></button>
                </div>
            </div>
        </div>
    </header>

    <div class="ffbe-main">
        <aside class="ffbe-panel" aria-labelledby="ffbe-panel-title">
            <div class="ffbe-panel__head"><h2 id="ffbe-panel-title">Elements</h2></div>
            <div class="ffbe-panel__body" id="ffbe-panel-body"></div>

            <form class="ffbe-panel__body ffbe-meta" id="ffbe-meta" hidden enctype="multipart/form-data" novalidate>
                <section class="ffbe-group">
                    <div class="ffbe-group__title">Publishing</div>
                    <label class="ffbe-field"><span class="ffbe-label">Title <b aria-hidden="true">*</b></span>
                        <input class="ffbe-input" name="title" value="{{ $article->title }}" maxlength="255" required placeholder="A clear, specific headline"></label>
                    <label class="ffbe-field"><span class="ffbe-label">Permalink</span>
                        <span class="ffbe-input-group"><span class="ffbe-input-group__addon">/articles/</span><input class="ffbe-input" name="slug" value="{{ $article->slug }}" maxlength="200" placeholder="generated-from-title"></span></label>
                    <div class="ffbe-field-row">
                        <label class="ffbe-field"><span class="ffbe-label">Status</span>
                            <select class="ffbe-input" name="status">
                                @foreach(\App\Models\Article::STATUSES as $status)
                                    <option value="{{ $status }}" @selected(($article->status ?: 'draft') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select></label>
                        <label class="ffbe-field"><span class="ffbe-label">Category</span>
                            <select class="ffbe-input" name="category_id">
                                <option value="">Uncategorised</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected($article->category_id == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select></label>
                    </div>
                    <div class="ffbe-field-row">
                        <label class="ffbe-field"><span class="ffbe-label">Publish date</span>
                            <input class="ffbe-input" type="datetime-local" name="published_at" value="{{ optional($article->published_at)->format('Y-m-d\TH:i') }}"></label>
                        <label class="ffbe-field" id="ffbe-schedule-field"><span class="ffbe-label">Scheduled for</span>
                            <input class="ffbe-input" type="datetime-local" name="scheduled_for" value="{{ optional($article->scheduled_for)->format('Y-m-d\TH:i') }}"></label>
                    </div>
                    <div class="ffbe-checks">
                        <label class="ffbe-check"><input type="checkbox" name="is_featured" value="1" @checked($article->is_featured)> Featured</label>
                        <label class="ffbe-check"><input type="checkbox" name="is_trending" value="1" @checked($article->is_trending)> Trending</label>
                    </div>
                </section>

                <section class="ffbe-group">
                    <div class="ffbe-group__title">Layout</div>
                    <label class="ffbe-field"><span class="ffbe-label">Page layout</span>
                        <select class="ffbe-input" name="page_template">
                            @foreach(\App\Models\Article::PAGE_TEMPLATES as $value => $label)
                                <option value="{{ $value }}" @selected(($article->page_template ?: 'default') === $value)>{{ $label }}</option>
                            @endforeach
                        </select></label>
                    <label class="ffbe-field"><span class="ffbe-label">Excerpt <small data-counter-for="excerpt" data-max="500" data-ideal="220"></small></span>
                        <textarea class="ffbe-input" name="excerpt" rows="3" maxlength="500" placeholder="One or two sentences shown on article cards and under the title.">{{ $article->excerpt }}</textarea></label>
                    <div class="ffbe-field">
                        <span class="ffbe-label">Featured image</span>
                        <img id="ffbe-featured-preview" class="ffbe-featured" alt="Featured image preview" @if($article->featured_image) src="{{ asset('storage/'.$article->featured_image) }}" @else hidden @endif>
                        <div class="ffbe-btn-row">
                            <label class="ffbe-btn ffbe-btn--light ffbe-btn--sm ffbe-file"><i class="fa-solid fa-upload"></i> Choose image
                                <input type="file" name="featured_image_file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
                            <button type="button" class="ffbe-btn ffbe-btn--light ffbe-btn--sm" id="ffbe-featured-remove" @unless($article->featured_image) hidden @endunless><i class="fa-solid fa-trash"></i> Remove</button>
                        </div>
                        <input type="checkbox" name="remove_featured_image" value="1" hidden>
                        <small class="ffbe-control__help">JPG, PNG, WebP or GIF; up to 5 MB.</small>
                    </div>
                </section>

                <section class="ffbe-group">
                    <div class="ffbe-group__title">Tags</div>
                    <div class="ffbe-field">
                        <input class="ffbe-input ffbe-search" type="search" id="ffbe-tag-search" placeholder="Filter tags…" aria-label="Filter tags">
                        <div class="ffbe-checklist ffbe-checklist--scroll" id="ffbe-tag-list">
                            @foreach($tags as $tag)
                                <label class="ffbe-check"><input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTags))> {{ $tag->name }}</label>
                            @endforeach
                        </div>
                    </div>
                    <label class="ffbe-field"><span class="ffbe-label">Add new tags</span>
                        <input class="ffbe-input" name="new_tags" maxlength="500" placeholder="Comma separated, e.g. fintech, AI"></label>
                </section>

                <section class="ffbe-group">
                    <div class="ffbe-group__title">Search engine</div>
                    <label class="ffbe-field"><span class="ffbe-label">SEO title <small data-counter-for="seo_title" data-max="255" data-ideal="60"></small></span>
                        <input class="ffbe-input" name="seo_title" value="{{ $article->seo_title }}" maxlength="255" placeholder="Defaults to the article title"></label>
                    <label class="ffbe-field"><span class="ffbe-label">SEO description <small data-counter-for="seo_description" data-max="320" data-ideal="160"></small></span>
                        <textarea class="ffbe-input" name="seo_description" rows="3" maxlength="320" placeholder="Defaults to the excerpt">{{ $article->seo_description }}</textarea></label>
                    <div class="ffbe-snippet" id="ffbe-snippet" aria-label="Search result preview">
                        <div data-snippet="url"></div>
                        <div data-snippet="title"></div>
                        <div data-snippet="desc"></div>
                    </div>
                </section>
            </form>
        </aside>

        <main class="ffbe-canvas">
            <div class="ffbe-frame-wrap" id="ffbe-frame-wrap" data-device="desktop">
                <iframe id="ffbe-frame" src="{{ route('admin.builder.canvas') }}" title="Page canvas"></iframe>
            </div>
        </main>

        <aside class="ffbe-navigator" id="ffbe-navigator" hidden aria-label="Navigator">
            <div class="ffbe-panel__head"><h2>Navigator</h2><button type="button" class="ffbe-icon-btn" data-action="close-navigator" aria-label="Close navigator"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="ffbe-navigator__tree" id="ffbe-navigator-tree"></div>
            <p class="ffbe-control__help ffbe-navigator__help">Drag items to reorder. Click to select.</p>
        </aside>
    </div>

    <form id="ffbe-preview-form" method="POST" action="{{ route('admin.articles.preview') }}" target="ffb-preview" hidden>
        @csrf
        <input type="hidden" name="article_id">
        <input type="hidden" name="title">
        <input type="hidden" name="excerpt">
        <input type="hidden" name="category_id">
        <input type="hidden" name="page_template">
        <input type="hidden" name="layout">
    </form>
</div>

<script type="application/json" id="ffb-config">{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) !!}</script>
</body>
</html>
