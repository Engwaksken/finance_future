@extends('layouts.admin')
@section('title', 'Categories')
@section('heading', 'Categories')

@section('content')
<div class="stats-grid">
    @foreach(['Total Categories' => $categories->count(), 'Active' => $categories->where('is_active', true)->count(), 'Featured' => $categories->where('is_featured', true)->count(), 'Articles' => $categories->sum('articles_count')] as $label => $count)
        <div class="stat-card"><div><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ number_format($count) }}</div></div><i class="fa-solid fa-layer-group stat-icon"></i></div>
    @endforeach
</div>
<div class="panel">
    <div class="panel-header"><div><h2 class="panel-title">Content Categories</h2><p class="text-muted">Choose an image, Font Awesome icon, or number for each topic.</p></div><button type="button" class="btn btn-primary" data-modal-open="addCategoryModal"><i class="fa-solid fa-plus"></i> Add Category</button></div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Display</th><th>Name</th><th>Articles</th><th>Featured</th><th>Status</th><th>Order</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td><x-category-visual :category="$category" :fallback="$loop->iteration" /></td>
                        <td><strong>{{ $category->name }}</strong><div class="text-muted">{{ \Illuminate\Support\Str::limit($category->description, 60) }}</div></td>
                        <td>{{ number_format($category->articles_count) }}</td>
                        <td><span class="badge {{ $category->is_featured ? 'badge-warning' : 'badge-grey' }}">{{ $category->is_featured ? 'Featured' : 'Standard' }}</span></td>
                        <td><span class="badge {{ $category->is_active ? 'badge-success' : 'badge-danger' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span></td>
                        <td>{{ $category->sort_order }}</td>
                        <td><div class="table-actions"><button type="button" class="btn-icon" aria-label="Edit {{ $category->name }}" data-modal-open="editCategoryModal{{ $category->id }}"><i class="fa-solid fa-pen"></i></button><button type="button" class="btn-icon btn-icon-danger" aria-label="Delete {{ $category->name }}" data-modal-open="deleteCategoryModal{{ $category->id }}"><i class="fa-solid fa-trash"></i></button></div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">No categories have been created.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach($categories as $category)
    <div class="modal-backdrop-custom" id="editCategoryModal{{ $category->id }}" role="dialog" aria-modal="true" aria-label="Edit category">
        <div class="modal-box">
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.categories.update', $category) }}">
                @csrf @method('PUT')
                <div class="modal-header"><h3 class="modal-title">Edit Category</h3><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></div>
                <div class="modal-body">@include('admin.categories.partials.fields', ['item' => $category, 'formId' => 'editCategoryModal'.$category->id])</div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
    <div class="modal-backdrop-custom" id="deleteCategoryModal{{ $category->id }}" role="dialog" aria-modal="true" aria-label="Delete category">
        <div class="modal-box">
            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
                @csrf @method('DELETE')
                <div class="modal-header"><h3 class="modal-title">Delete Category</h3><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></div>
                <div class="modal-body"><p>Delete <strong>{{ $category->name }}</strong>?</p>@if($category->articles_count)<p class="text-muted">Move or remove this category's articles first.</p>@else<p class="text-muted">Its uploaded image and category-specific slides will also be removed.</p>@endif</div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger" @disabled($category->articles_count > 0)>Delete</button></div>
            </form>
        </div>
    </div>
@endforeach
<div class="modal-backdrop-custom" id="addCategoryModal" role="dialog" aria-modal="true" aria-label="Add category">
    <div class="modal-box">
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.categories.store') }}">
            @csrf
            <div class="modal-header"><h3 class="modal-title">Add Category</h3><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></div>
            <div class="modal-body">@include('admin.categories.partials.fields', ['item' => null, 'formId' => 'addCategoryModal'])</div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Add Category</button></div>
        </form>
    </div>
</div>
@endsection
