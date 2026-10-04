@extends('layouts.admin')
@section('title',$article->exists?'Edit Article':'New Article')
@section('heading',$article->exists?'Edit Article':'Create Article')
@section('content')
<div class="panel">
<form method="POST" action="{{ $article->exists ? route('admin.articles.update',$article) : route('admin.articles.store') }}">
@csrf @if($article->exists) @method('PUT') @endif
<div class="field"><label>Title</label><input name="title" value="{{ old('title',$article->title) }}" required></div>
<div class="field"><label>Category</label><select name="category_id"><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id',$article->category_id)==$category->id)>{{ $category->name }}</option>@endforeach</select></div>
<div class="field"><label>Excerpt</label><textarea name="excerpt" rows="3">{{ old('excerpt',$article->excerpt) }}</textarea></div>
<div class="field"><label>Article Body</label><textarea name="body" rows="16" required>{{ old('body',$article->body) }}</textarea></div>
<div class="field"><label>Featured Image Path</label><input name="featured_image" value="{{ old('featured_image',$article->featured_image) }}" placeholder="articles/example.jpg"></div>
<div class="field"><label>Status</label><select name="status">@foreach(['draft','review','scheduled','published','archived'] as $status)<option value="{{ $status }}" @selected(old('status',$article->status ?: 'draft')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
<div class="field"><label><input style="width:auto" type="checkbox" name="is_featured" value="1" @checked(old('is_featured',$article->is_featured))> Featured</label> &nbsp; <label><input style="width:auto" type="checkbox" name="is_trending" value="1" @checked(old('is_trending',$article->is_trending))> Trending</label></div>
<div class="field"><label>Scheduled For</label><input type="datetime-local" name="scheduled_for" value="{{ old('scheduled_for', optional($article->scheduled_for)->format('Y-m-d\TH:i')) }}"></div>
<div class="field"><label>SEO Title</label><input name="seo_title" value="{{ old('seo_title',$article->seo_title) }}"></div>
<div class="field"><label>SEO Description</label><textarea name="seo_description" rows="3">{{ old('seo_description',$article->seo_description) }}</textarea></div>
<div class="field"><label>Tags</label><select name="tags[]" multiple size="7">@foreach($tags as $tag)<option value="{{ $tag->id }}" @selected(in_array($tag->id, old('tags',$article->exists?$article->tags->pluck('id')->all():[])))>{{ $tag->name }}</option>@endforeach</select></div>
<button class="btn" type="submit">{{ $article->exists?'Save Changes':'Create Article' }}</button>
</form>
</div>
@endsection
