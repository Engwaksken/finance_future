<div class="panel-header"><div><h2 class="panel-title">Page Slides</h2><p class="text-muted">Manage page banners with image, color, or video backgrounds. Lower order numbers appear first.</p></div><button type="button" class="btn btn-primary" data-modal-open="addSlideModal"><i class="fa-solid fa-plus"></i> Add Slide</button></div>
<div class="table-responsive">
    <table>
        <thead><tr><th>Preview</th><th>Title</th><th>Page</th><th>Background</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($slides as $slide)
                <tr>
                    <td>
                        @if($slide->background_type === 'image')
                            <img class="slide-thumbnail" src="{{ asset('storage/'.$slide->media_path) }}" alt="{{ $slide->title }}">
                        @elseif($slide->background_type === 'video')
                            <span class="slide-thumbnail slide-swatch" style="background-color:{{ $slide->background_color }};color:{{ $slide->text_color }}"><i class="fa-solid fa-video"></i></span>
                        @else
                            <span class="slide-thumbnail slide-swatch" style="background-color:{{ $slide->background_color }};color:{{ $slide->text_color }}"><i class="fa-solid fa-palette"></i></span>
                        @endif
                    </td>
                    <td><strong>{{ $slide->title }}</strong></td>
                    <td>{{ \App\Models\PageSlide::PAGES[$slide->page] ?? $slide->page }}@if($slide->category)<div class="text-muted">{{ $slide->category->name }}</div>@endif</td>
                    <td>{{ ucfirst($slide->background_type) }}</td><td>{{ $slide->sort_order }}</td>
                    <td><span class="badge {{ $slide->is_active ? 'badge-success' : 'badge-grey' }}">{{ $slide->is_active ? 'Active' : 'Hidden' }}</span></td>
                    <td><div class="table-actions"><button type="button" class="btn-icon" aria-label="Edit {{ $slide->title }}" data-modal-open="editSlideModal{{ $slide->id }}"><i class="fa-solid fa-pen"></i></button><button type="button" class="btn-icon btn-icon-danger" aria-label="Delete {{ $slide->title }}" data-modal-open="deleteSlideModal{{ $slide->id }}"><i class="fa-solid fa-trash"></i></button></div></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state">No slides yet. Add a slide to display a banner on a frontend page.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@foreach($slides as $slide)
    <div class="modal-backdrop-custom" id="editSlideModal{{ $slide->id }}" role="dialog" aria-modal="true" aria-label="Edit slide">
        <div class="modal-box modal-lg">
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.settings.slides.update', $slide) }}">
                @csrf @method('PUT')
                <div class="modal-header"><h3 class="modal-title">Edit Page Slide</h3><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></div>
                <div class="modal-body">@include('admin.settings.partials.slide-fields', ['item' => $slide, 'formId' => 'editSlideModal'.$slide->id])</div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save Slide</button></div>
            </form>
        </div>
    </div>
    <div class="modal-backdrop-custom" id="deleteSlideModal{{ $slide->id }}" role="dialog" aria-modal="true" aria-label="Delete slide">
        <div class="modal-box">
            <form method="POST" action="{{ route('admin.settings.slides.destroy', $slide) }}">
                @csrf @method('DELETE')
                <div class="modal-header"><h3 class="modal-title">Delete Slide</h3><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></div>
                <div class="modal-body"><p>Delete <strong>{{ $slide->title }}</strong> and its uploaded media?</p></div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger">Delete Slide</button></div>
            </form>
        </div>
    </div>
@endforeach
<div class="modal-backdrop-custom" id="addSlideModal" role="dialog" aria-modal="true" aria-label="Add slide">
    <div class="modal-box modal-lg">
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.settings.slides.store') }}">
            @csrf
            <div class="modal-header"><h3 class="modal-title">Add Page Slide</h3><button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button></div>
            <div class="modal-body">@include('admin.settings.partials.slide-fields', ['item' => null, 'formId' => 'addSlideModal'])</div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Add Slide</button></div>
        </form>
    </div>
</div>
