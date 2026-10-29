<div class="form-grid">
    <div class="field field-full">
        <label>Title</label>
        <input name="title" value="{{ old('title',$article->title) }}" required>
    </div>

    <div class="field">
        <label>Category</label>
        <select name="category_id">
            <option value="">Select category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id',$article->category_id)==$category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label>Status</label>
        <select name="status" required>
            @foreach(['draft','review','scheduled','published','archived'] as $status)
                <option value="{{ $status }}" @selected(old('status',$article->status ?: 'draft')===$status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>

    <div class="field field-full">
        <label>Excerpt</label>
        <textarea name="excerpt" rows="3">{{ old('excerpt',$article->excerpt) }}</textarea>
    </div>

    <div class="field field-full">
        <label>Article Body</label>
        <div style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem">
            <button class="btn btn-light" type="button" data-insert-link="body">Insert link</button>
            <small class="field-help">Select text, then insert a link. Links can also be typed as HTML.</small>
        </div>
        <textarea name="body" rows="12" required>{{ old('body',$article->body) }}</textarea>
    </div>

    <div class="field">
        <label>Featured Image</label>
        @if($article->featured_image)
            <img src="{{ asset('storage/'.$article->featured_image) }}" alt="Current featured image" style="display:block;max-width:180px;max-height:120px;object-fit:cover;margin-bottom:.5rem">
        @endif
        <input type="file" name="featured_image_file" accept="image/jpeg,image/png,image/webp,image/gif" data-image-preview>
        <small class="field-help">JPG, PNG, WebP or GIF; maximum 5 MB. Leave empty to keep the current image.</small>
        <img alt="Selected image preview" data-preview-output hidden style="max-width:180px;max-height:120px;object-fit:cover;margin-top:.5rem">
    </div>

    <div class="field">
        <label>Tags</label>
        <select name="tags[]" multiple size="5">
            @foreach($tags as $tag)
                <option value="{{ $tag->id }}" @selected(in_array($tag->id, old('tags',$article->exists ? $article->tags->pluck('id')->all() : [])))>{{ $tag->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label>Scheduled For</label>
        <input type="datetime-local" name="scheduled_for" value="{{ old('scheduled_for',optional($article->scheduled_for)->format('Y-m-d\TH:i')) }}">
    </div>

    <div class="field">
        <label>Published At</label>
        <input type="datetime-local" name="published_at" value="{{ old('published_at',optional($article->published_at)->format('Y-m-d\TH:i')) }}">
    </div>

    <div class="field field-full">
        <div class="checkbox-row">
            <label class="checkbox-label"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured',$article->is_featured))> Featured</label>
            <label class="checkbox-label"><input type="checkbox" name="is_trending" value="1" @checked(old('is_trending',$article->is_trending))> Trending</label>
        </div>
    </div>

    <div class="field">
        <label>SEO Title</label>
        <input name="seo_title" value="{{ old('seo_title',$article->seo_title) }}">
    </div>

    <div class="field">
        <label>SEO Description</label>
        <textarea name="seo_description" rows="3">{{ old('seo_description',$article->seo_description) }}</textarea>
    </div>
</div>
@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                const button = event.target.closest('[data-insert-link="body"]');
                if (!button) return;
                const textarea = button.closest('form').querySelector('textarea[name="body"]');
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const selected = textarea.value.slice(start, end) || 'link text';
                const url = window.prompt('Enter the link URL (https://, mailto:, or tel:):', 'https://');
                if (!url) return;
                if (!/^(https?:\/\/|mailto:|tel:|\/|#)/i.test(url)) {
                    window.alert('Please use a valid https://, http://, mailto:, tel:, or relative link.');
                    return;
                }
                const escapedUrl = url.replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
                const escapedText = selected.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
                const link = `<a href="${escapedUrl}">${escapedText}</a>`;
                textarea.setRangeText(link, start, end, 'select');
                textarea.focus();
            });

            document.addEventListener('change', function (event) {
                if (!event.target.matches('[data-image-preview]')) return;
                const preview = event.target.parentElement.querySelector('[data-preview-output]');
                const file = event.target.files[0];
                if (!file) { preview.hidden = true; return; }
                preview.src = URL.createObjectURL(file);
                preview.hidden = false;
            });
        </script>
    @endpush
@endonce
