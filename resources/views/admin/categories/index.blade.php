@extends('layouts.admin')

@section('title', 'Categories')

@section('heading', 'Categories')


@section('content')

<div class="stats-grid">

    <div class="stat-card border-blue">

        <div>

            <div class="stat-label">
                Total Categories
            </div>

            <div class="stat-value">
                {{ number_format($categories->count()) }}
            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-solid fa-layer-group"></i>

        </div>

    </div>


    <div class="stat-card border-green">

        <div>

            <div class="stat-label">
                Active
            </div>

            <div class="stat-value">

                {{
                    number_format(
                        $categories
                            ->where('is_active', true)
                            ->count()
                    )
                }}

            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-solid fa-circle-check"></i>

        </div>

    </div>


    <div class="stat-card border-orange">

        <div>

            <div class="stat-label">
                Featured
            </div>

            <div class="stat-value">

                {{
                    number_format(
                        $categories
                            ->where('is_featured', true)
                            ->count()
                    )
                }}

            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-solid fa-star"></i>

        </div>

    </div>


    <div class="stat-card border-purple">

        <div>

            <div class="stat-label">
                Articles
            </div>

            <div class="stat-value">

                {{
                    number_format(
                        $categories->sum('articles_count')
                    )
                }}

            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-regular fa-newspaper"></i>

        </div>

    </div>

</div>


<div class="panel">

    <div class="panel-header">

        <div>

            <h2 class="panel-title">
                Content Categories
            </h2>

            <div class="text-muted mt-2">

                Manage website article categories.

            </div>

        </div>


        <button
            type="button"
            class="btn btn-primary"
            data-modal-open="addCategoryModal">

            <i class="fa-solid fa-plus"></i>

            Add Category

        </button>

    </div>


    <div class="table-responsive">

        <table>

            <thead>

            <tr>

                <th>
                    Name
                </th>

                <th>
                    Description
                </th>

                <th>
                    Articles
                </th>

                <th>
                    Featured
                </th>

                <th>
                    Status
                </th>

                <th>
                    Order
                </th>

                <th style="width:110px;">
                    Actions
                </th>

            </tr>

            </thead>


            <tbody>

            @forelse($categories as $category)

                <tr>

                    <td>

                        <strong>
                            {{ $category->name }}
                        </strong>

                    </td>


                    <td class="text-muted">

                        {{
                            \Illuminate\Support\Str::limit(
                                $category->description,
                                60
                            )
                        }}

                    </td>


                    <td>

                        {{ number_format($category->articles_count) }}

                    </td>


                    <td>

                        @if($category->is_featured)

                            <span class="badge badge-warning">

                                <i class="fa-solid fa-star"></i>

                                Featured

                            </span>

                        @else

                            <span class="badge badge-grey">

                                Standard

                            </span>

                        @endif

                    </td>


                    <td>

                        @if($category->is_active)

                            <span class="badge badge-success">

                                Active

                            </span>

                        @else

                            <span class="badge badge-danger">

                                Hidden

                            </span>

                        @endif

                    </td>


                    <td>

                        {{ $category->sort_order }}

                    </td>


                    <td>

                        <div class="table-actions">

                            <button
                                type="button"
                                class="btn-icon"
                                title="Edit Category"
                                data-modal-open="editCategoryModal{{ $category->id }}">

                                <i class="fa-solid fa-pen"></i>

                            </button>


                            <button
                                type="button"
                                class="btn-icon btn-icon-danger"
                                title="Delete Category"
                                data-modal-open="deleteCategoryModal{{ $category->id }}">

                                <i class="fa-solid fa-trash"></i>

                            </button>

                        </div>

                    </td>

                </tr>


                {{-- Edit Modal --}}

                <div
                    class="modal-backdrop-custom"
                    id="editCategoryModal{{ $category->id }}">

                    <div class="modal-box">

                        <form
                            method="POST"
                            action="{{ route('admin.categories.update', $category) }}">

                            @csrf

                            @method('PUT')


                            <div class="modal-header">

                                <h3 class="modal-title">

                                    Edit Category

                                </h3>


                                <button
                                    type="button"
                                    class="modal-close"
                                    data-modal-close>

                                    &times;

                                </button>

                            </div>


                            <div class="modal-body">

                                <div class="field">

                                    <label>
                                        Category Name
                                    </label>

                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ $category->name }}"
                                        required>

                                </div>


                                <div class="field">

                                    <label>
                                        Description
                                    </label>

                                    <textarea
                                        name="description"
                                        rows="4">{{ $category->description }}</textarea>

                                </div>


                                <div class="field">

                                    <label>
                                        Sort Order
                                    </label>

                                    <input
                                        type="number"
                                        name="sort_order"
                                        min="0"
                                        value="{{ $category->sort_order }}">

                                </div>


                                <div class="checkbox-row">

                                    <label class="checkbox-label">

                                        <input
                                            type="checkbox"
                                            name="is_featured"
                                            value="1"
                                            @checked($category->is_featured)>

                                        Featured

                                    </label>


                                    <label class="checkbox-label">

                                        <input
                                            type="checkbox"
                                            name="is_active"
                                            value="1"
                                            @checked($category->is_active)>

                                        Active

                                    </label>

                                </div>

                            </div>


                            <div class="modal-footer">

                                <button
                                    type="button"
                                    class="btn btn-light"
                                    data-modal-close>

                                    Cancel

                                </button>


                                <button
                                    type="submit"
                                    class="btn btn-primary">

                                    <i class="fa-solid fa-floppy-disk"></i>

                                    Save Changes

                                </button>

                            </div>

                        </form>

                    </div>

                </div>


                {{-- Delete Modal --}}

                <div
                    class="modal-backdrop-custom"
                    id="deleteCategoryModal{{ $category->id }}">

                    <div class="modal-box modal-sm">

                        <form
                            method="POST"
                            action="{{ route('admin.categories.destroy', $category) }}">

                            @csrf

                            @method('DELETE')


                            <div class="modal-header">

                                <h3 class="modal-title">

                                    Delete Category

                                </h3>


                                <button
                                    type="button"
                                    class="modal-close"
                                    data-modal-close>

                                    &times;

                                </button>

                            </div>


                            <div class="modal-body">

                                <p>

                                    Are you sure you want to delete

                                    <strong>
                                        {{ $category->name }}
                                    </strong>?

                                </p>


                                @if($category->articles_count > 0)

                                    <div class="alert alert-danger mb-0">

                                        This category currently contains

                                        {{ $category->articles_count }}

                                        article(s).

                                        Move the articles before deleting
                                        the category.

                                    </div>

                                @endif

                            </div>


                            <div class="modal-footer">

                                <button
                                    type="button"
                                    class="btn btn-light"
                                    data-modal-close>

                                    Cancel

                                </button>


                                <button
                                    type="submit"
                                    class="btn btn-danger"
                                    @disabled($category->articles_count > 0)>

                                    <i class="fa-solid fa-trash"></i>

                                    Delete

                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            @empty

                <tr>

                    <td colspan="7"
                        style="text-align:center;padding:38px;">

                        No categories have been created.

                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>


{{-- Add Category Modal --}}

<div
    class="modal-backdrop-custom"
    id="addCategoryModal">

    <div class="modal-box">

        <form
            method="POST"
            action="{{ route('admin.categories.store') }}">

            @csrf


            <div class="modal-header">

                <h3 class="modal-title">

                    Add Category

                </h3>


                <button
                    type="button"
                    class="modal-close"
                    data-modal-close>

                    &times;

                </button>

            </div>


            <div class="modal-body">

                <div class="field">

                    <label>
                        Category Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        placeholder="Example: Finance"
                        required>

                </div>


                <div class="field">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        rows="4"
                        placeholder="Short description of this category">{{ old('description') }}</textarea>

                </div>


                <div class="field">

                    <label>
                        Sort Order
                    </label>

                    <input
                        type="number"
                        name="sort_order"
                        min="0"
                        value="{{ old('sort_order', 0) }}">

                </div>


                <div class="checkbox-row">

                    <label class="checkbox-label">

                        <input
                            type="checkbox"
                            name="is_featured"
                            value="1">

                        Featured

                    </label>


                    <label class="checkbox-label">

                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            checked>

                        Active

                    </label>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-light"
                    data-modal-close>

                    Cancel

                </button>


                <button
                    type="submit"
                    class="btn btn-primary">

                    <i class="fa-solid fa-plus"></i>

                    Add Category

                </button>

            </div>

        </form>

    </div>

</div>

@endsection