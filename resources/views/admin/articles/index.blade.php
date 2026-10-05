@extends('layouts.admin')
@section('title','Articles')
@section('heading','Articles')

@section('content')
<div class="panel">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">Manage Articles</h2>
            <div class="text-muted">Create and edit articles without leaving this page.</div>
        </div>
        <button class="btn btn-primary" type="button" data-modal-open="addArticleModal">
            <i class="fa-solid fa-plus"></i> Add Article
        </button>
    </div>

    <div class="panel-body">
        <form method="GET" class="filters">
            <input name="q" value="{{ request('q') }}" placeholder="Search articles">
            <select name="status">
                <option value="">All statuses</option>
                @foreach(['draft','review','scheduled','published','archived'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
            <tr><th>Article</th><th>Category</th><th>Author</th><th>Status</th><th>Views</th><th>Actions</th></tr>
            </thead>
            <tbody>
            @forelse($articles as $article)
                <tr>
                    <td><span class="table-title">{{ $article->title }}</span></td>
                    <td>{{ $article->category?->name ?? 'Uncategorised' }}</td>
                    <td>{{ $article->author?->name ?? 'Unknown' }}</td>
                    <td><span class="badge badge-grey">{{ ucfirst($article->status) }}</span></td>
                    <td>{{ number_format($article->views_count) }}</td>
                    <td>
                        <div class="table-actions">
                            <button class="btn-icon" type="button" data-modal-open="editArticle{{ $article->id }}"><i class="fa-solid fa-pen"></i></button>
                            <button class="btn-icon btn-icon-danger" type="button" data-modal-open="deleteArticle{{ $article->id }}"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>

                <div class="modal-backdrop-custom" id="editArticle{{ $article->id }}">
                    <div class="modal-box modal-lg">
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.articles.update',$article) }}">
                            @csrf @method('PUT')
                            <div class="modal-header"><h3 class="modal-title">Edit Article</h3><button class="modal-close" type="button" data-modal-close>&times;</button></div>
                            <div class="modal-body">
                                @include('admin.articles.partials.fields',['article'=>$article])
                            </div>
                            <div class="modal-footer"><button class="btn btn-light" type="button" data-modal-close>Cancel</button><button class="btn btn-primary">Save Changes</button></div>
                        </form>
                    </div>
                </div>

                <div class="modal-backdrop-custom" id="deleteArticle{{ $article->id }}">
                    <div class="modal-box modal-sm">
                        <form method="POST" action="{{ route('admin.articles.destroy',$article) }}">
                            @csrf @method('DELETE')
                            <div class="modal-header"><h3 class="modal-title">Delete Article</h3><button class="modal-close" type="button" data-modal-close>&times;</button></div>
                            <div class="modal-body">Move <strong>{{ $article->title }}</strong> to trash?</div>
                            <div class="modal-footer"><button class="btn btn-light" type="button" data-modal-close>Cancel</button><button class="btn btn-danger">Delete</button></div>
                        </form>
                    </div>
                </div>
            @empty
                <tr><td colspan="6" class="empty-state">No articles found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($articles->hasPages())<div class="panel-body">{{ $articles->links('pagination::bootstrap-4') }}</div>@endif
</div>

<div class="modal-backdrop-custom" id="addArticleModal">
    <div class="modal-box modal-lg">
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.articles.store') }}">
            @csrf
            <div class="modal-header"><h3 class="modal-title">Add Article</h3><button class="modal-close" type="button" data-modal-close>&times;</button></div>
            <div class="modal-body">
                @include('admin.articles.partials.fields',['article'=>new \App\Models\Article()])
            </div>
            <div class="modal-footer"><button class="btn btn-light" type="button" data-modal-close>Cancel</button><button class="btn btn-primary">Create Article</button></div>
        </form>
    </div>
</div>
@endsection
