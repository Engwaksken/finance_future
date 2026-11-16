@extends('layouts.admin')
@section('title','Articles')
@section('heading','Articles')

@php
    $statusBadge = ['published' => 'badge-green', 'draft' => 'badge-grey', 'review' => 'badge-blue', 'scheduled' => 'badge-gold', 'archived' => 'badge-grey'];
@endphp

@section('content')
<x-admin-stats :stats="$stats" :period="$period" />
<div class="panel">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">{{ $inTrash ? 'Trash' : 'Manage Articles' }}</h2>
            <div class="text-muted">{{ $inTrash ? 'Restore articles or delete them permanently.' : 'Design articles visually with the drag-and-drop builder.' }}</div>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.articles.create') }}">
            <i class="fa-solid fa-plus"></i> Add Article
        </a>
    </div>

    <div class="panel-body">
        <nav class="article-views" aria-label="Article views">
            <a href="{{ route('admin.articles.index', request()->except(['view', 'page'])) }}" class="{{ $inTrash ? '' : 'active' }}" @unless($inTrash) aria-current="page" @endunless>All articles</a>
            <a href="{{ route('admin.articles.index', array_merge(request()->except('page'), ['view' => 'trash'])) }}" class="{{ $inTrash ? 'active' : '' }}" @if($inTrash) aria-current="page" @endif>Trash <span class="count">{{ number_format($trashCount) }}</span></a>
        </nav>

        <form method="GET" class="admin-filters">
            @if($inTrash)<input type="hidden" name="view" value="trash">@endif
            <div class="filter-field"><label for="article-search">Search</label><input id="article-search" name="q" value="{{ request('q') }}" placeholder="Title or slug" maxlength="200"></div>
            <div class="filter-field"><label for="article-status">Status</label><select id="article-status" name="status">
                <option value="">All statuses</option>
                @foreach(\App\Models\Article::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select></div>
            <div class="filter-field"><label for="article-category">Category</label><select id="article-category" name="category">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select></div>
            <div class="filter-field"><label for="article-sort">Sort</label><select id="article-sort" name="sort">
                @foreach(['latest' => 'Newest first', 'oldest' => 'Oldest first', 'updated' => 'Recently updated', 'title' => 'Title A–Z', 'views' => 'Most viewed'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', 'latest') === $value)>{{ $label }}</option>
                @endforeach
            </select></div>
            <x-admin-period-filter :period="$period" />
            <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
            <a class="btn btn-light" href="{{ route('admin.articles.index', $inTrash ? ['view' => 'trash'] : []) }}">Reset</a>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.articles.bulk') }}" id="bulkForm">
        @csrf
        <div class="panel-body bulk-bar">
            <label for="bulk-action" class="sr-only">Bulk action</label>
            <select name="action" id="bulk-action" required>
                <option value="">Bulk actions</option>
                @if($inTrash)
                    <option value="restore">Restore</option>
                    <option value="force_delete">Delete permanently</option>
                @else
                    <option value="publish">Publish</option>
                    <option value="draft">Move to draft</option>
                    <option value="review">Mark for review</option>
                    <option value="archive">Archive</option>
                    <option value="feature">Mark featured</option>
                    <option value="unfeature">Remove featured</option>
                    <option value="trash">Move to trash</option>
                @endif
            </select>
            <button class="btn btn-light" type="submit" data-bulk-apply disabled>Apply</button>
            <span class="text-muted" data-bulk-count aria-live="polite">No articles selected</span>
        </div>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
            <tr>
                <th class="col-check"><input type="checkbox" data-bulk-all aria-label="Select all articles on this page"></th>
                <th>Article</th><th>Category</th><th>Author</th><th>Status</th><th>Views</th><th>Updated</th><th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($articles as $article)
                <tr>
                    <td class="col-check"><input type="checkbox" name="ids[]" value="{{ $article->id }}" form="bulkForm" data-bulk-item aria-label="Select {{ $article->title }}"></td>
                    <td>
                        <div class="article-cell">
                            @if($article->featured_image)
                                <img src="{{ asset('storage/'.$article->featured_image) }}" alt="" class="article-thumb" loading="lazy">
                            @else
                                <span class="article-thumb article-thumb--empty" aria-hidden="true"><i class="fa-regular fa-image"></i></span>
                            @endif
                            <div>
                                @if($inTrash)
                                    <span class="table-title">{{ $article->title }}</span>
                                @else
                                    <a class="table-title" href="{{ route('admin.articles.edit', $article) }}">{{ $article->title }}</a>
                                @endif
                                <div class="article-sub">
                                    /{{ $article->slug }}
                                    @if($article->built_with_builder)<span class="badge badge-blue" title="Built with the visual builder"><i class="fa-solid fa-layer-group"></i> Builder</span>@endif
                                    @if($article->is_featured)<span class="badge badge-gold"><i class="fa-solid fa-star"></i> Featured</span>@endif
                                    @if($article->is_trending)<span class="badge badge-grey"><i class="fa-solid fa-fire"></i> Trending</span>@endif
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>{{ $article->category?->name ?? 'Uncategorised' }}</td>
                    <td>{{ $article->author?->name ?? 'Unknown' }}</td>
                    <td>
                        <span class="badge {{ $statusBadge[$article->status] ?? 'badge-grey' }}">{{ ucfirst($article->status) }}</span>
                        @if($article->status === 'scheduled' && $article->scheduled_for)<div class="article-sub">{{ $article->scheduled_for->format('d M Y H:i') }}</div>@endif
                    </td>
                    <td>{{ number_format($article->views_count) }}</td>
                    <td><span title="{{ $article->updated_at }}">{{ $article->updated_at?->diffForHumans() }}</span></td>
                    <td>
                        <div class="table-actions">
                            @if($inTrash)
                                <form method="POST" action="{{ route('admin.articles.restore', $article->id) }}">@csrf @method('PATCH')
                                    <button class="btn-icon" type="submit" title="Restore" aria-label="Restore {{ $article->title }}"><i class="fa-solid fa-trash-arrow-up"></i></button>
                                </form>
                                <button class="btn-icon btn-icon-danger" type="button" data-modal-open="forceDeleteArticle{{ $article->id }}" title="Delete permanently" aria-label="Delete {{ $article->title }} permanently"><i class="fa-solid fa-ban"></i></button>
                            @else
                                <a class="btn-icon" href="{{ route('admin.articles.edit', $article) }}" title="Edit with builder" aria-label="Edit {{ $article->title }}"><i class="fa-solid fa-pen"></i></a>
                                @if($article->status === 'published')
                                    <a class="btn-icon" href="{{ route('articles.show', $article->slug) }}" target="_blank" rel="noopener" title="View" aria-label="View {{ $article->title }}"><i class="fa-solid fa-eye"></i></a>
                                @endif
                                <form method="POST" action="{{ route('admin.articles.duplicate', $article) }}">@csrf
                                    <button class="btn-icon" type="submit" title="Duplicate" aria-label="Duplicate {{ $article->title }}"><i class="fa-solid fa-clone"></i></button>
                                </form>
                                <button class="btn-icon btn-icon-danger" type="button" data-modal-open="deleteArticle{{ $article->id }}" title="Move to trash" aria-label="Move {{ $article->title }} to trash"><i class="fa-solid fa-trash"></i></button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-state">{{ $inTrash ? 'Trash is empty.' : 'No articles found.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($articles->hasPages())<div class="panel-body">{{ $articles->links('pagination::bootstrap-4') }}</div>@endif
</div>

@foreach($articles as $article)
    @if($inTrash)
        <div class="modal-backdrop-custom" id="forceDeleteArticle{{ $article->id }}">
            <div class="modal-box modal-sm">
                <form method="POST" action="{{ route('admin.articles.force-destroy', $article->id) }}">
                    @csrf @method('DELETE')
                    <div class="modal-header"><h3 class="modal-title">Delete permanently</h3><button class="modal-close" type="button" data-modal-close aria-label="Close">&times;</button></div>
                    <div class="modal-body">Permanently delete <strong>{{ $article->title }}</strong>? This cannot be undone.</div>
                    <div class="modal-footer"><button class="btn btn-light" type="button" data-modal-close>Cancel</button><button class="btn btn-danger">Delete permanently</button></div>
                </form>
            </div>
        </div>
    @else
        <div class="modal-backdrop-custom" id="deleteArticle{{ $article->id }}">
            <div class="modal-box modal-sm">
                <form method="POST" action="{{ route('admin.articles.destroy',$article) }}">
                    @csrf @method('DELETE')
                    <div class="modal-header"><h3 class="modal-title">Delete Article</h3><button class="modal-close" type="button" data-modal-close aria-label="Close">&times;</button></div>
                    <div class="modal-body">Move <strong>{{ $article->title }}</strong> to trash? You can restore it later.</div>
                    <div class="modal-footer"><button class="btn btn-light" type="button" data-modal-close>Cancel</button><button class="btn btn-danger">Move to trash</button></div>
                </form>
            </div>
        </div>
    @endif
@endforeach
@endsection

@push('styles')
<style>
.article-views{display:flex;gap:6px;margin-bottom:14px;border-bottom:1px solid #e7e9ec}
.article-views a{padding:8px 12px;border-bottom:2px solid transparent;margin-bottom:-1px;color:#667085;font-weight:600;text-decoration:none}
.article-views a.active{color:#0b3b60;border-bottom-color:#0b3b60}
.article-views .count{display:inline-block;min-width:20px;padding:1px 6px;border-radius:999px;background:#f2f4f7;font-size:12px;text-align:center}
.bulk-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding-top:0}
.bulk-bar select{max-width:220px}
.col-check{width:36px}
.col-check input{width:16px;height:16px}
.article-cell{display:flex;align-items:center;gap:12px;min-width:240px}
.article-thumb{width:56px;height:40px;object-fit:cover;border-radius:6px;flex-shrink:0;background:#f2f4f7}
.article-thumb--empty{display:flex;align-items:center;justify-content:center;color:#98a2b3}
a.table-title{color:inherit;text-decoration:none}a.table-title:hover{text-decoration:underline}
.article-sub{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:3px;font-size:12px;color:#667085}
.article-sub .badge{font-size:11px}
.badge-green{background:#ecfdf3;color:#067647}.badge-blue{background:#eff8ff;color:#175cd3}.badge-gold{background:#fdf6e7;color:#8a6a2c}
.table-actions form{display:inline}
.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const all = document.querySelector('[data-bulk-all]');
    const items = () => [...document.querySelectorAll('[data-bulk-item]')];
    const apply = document.querySelector('[data-bulk-apply]');
    const count = document.querySelector('[data-bulk-count]');
    const action = document.getElementById('bulk-action');
    const refresh = () => {
        const selected = items().filter((i) => i.checked).length;
        count.textContent = selected ? `${selected} selected` : 'No articles selected';
        apply.disabled = !selected || !action.value;
        if (all) {
            all.checked = selected > 0 && selected === items().length;
            all.indeterminate = selected > 0 && selected < items().length;
        }
    };
    all?.addEventListener('change', () => { items().forEach((i) => { i.checked = all.checked; }); refresh(); });
    document.addEventListener('change', (e) => { if (e.target.matches('[data-bulk-item]')) refresh(); });
    action.addEventListener('change', refresh);
    document.getElementById('bulkForm').addEventListener('submit', (e) => {
        if (action.value === 'force_delete' && !window.confirm('Permanently delete the selected articles? This cannot be undone.')) e.preventDefault();
    });
    refresh();
})();
</script>
@endpush
