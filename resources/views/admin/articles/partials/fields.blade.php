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
        <textarea name="body" rows="12" required>{{ old('body',$article->body) }}</textarea>
    </div>

    <div class="field">
        <label>Featured Image</label>
        <input type="file" name="featured_image_file" accept="image/*">
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
