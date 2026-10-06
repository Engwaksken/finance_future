@php
    $failed = old('_form') === $formId;
    $value = fn ($key, $default = null) => $failed ? old($key, $default) : ($item?->{$key} ?? $default);
@endphp
<input type="hidden" name="_form" value="{{ $formId }}">
<input type="hidden" name="section" value="slides">
<div class="form-grid" x-data="{ backgroundType: @js($value('background_type', 'color')), page: @js($value('page', 'home')) }">
    <div class="field"><label for="{{ $formId }}-page">Page</label><select id="{{ $formId }}-page" name="page" x-model="page">@foreach(\App\Models\PageSlide::PAGES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
    <div class="field" x-show="page === 'categories.show'"><label for="{{ $formId }}-category">Category</label><select id="{{ $formId }}-category" name="category_id" :disabled="page !== 'categories.show'"><option value="">All category pages</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($value('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
    <div class="field field-full"><label for="{{ $formId }}-title">Title</label><input id="{{ $formId }}-title" name="title" value="{{ $value('title') }}" maxlength="255" required></div>
    <div class="field field-full"><label for="{{ $formId }}-description">Description</label><textarea id="{{ $formId }}-description" name="description" rows="3" maxlength="2000">{{ $value('description') }}</textarea></div>
    <div class="field"><label for="{{ $formId }}-type">Background Type</label><select id="{{ $formId }}-type" name="background_type" x-model="backgroundType"><option value="color">Solid Color</option><option value="image">Image</option><option value="video">Video</option></select></div>
    <div class="field"><label for="{{ $formId }}-order">Sort Order</label><input id="{{ $formId }}-order" type="number" name="sort_order" value="{{ $value('sort_order', 0) }}" min="0" max="999999" required></div>
    <div class="field field-full" x-show="backgroundType !== 'color'">
        <label for="{{ $formId }}-media">Upload Background</label>
        @if($item?->media_path)
            @if($item->background_type === 'video')<video class="media-preview" controls preload="metadata" src="{{ asset('storage/'.$item->media_path) }}"></video>@else<img class="media-preview" src="{{ asset('storage/'.$item->media_path) }}" alt="Current slide background">@endif
        @endif
        <input id="{{ $formId }}-media" type="file" name="media" :disabled="backgroundType === 'color'" :accept="backgroundType === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp,image/gif'" :required="backgroundType !== 'color' && ({{ $item?->media_path ? 'false' : 'true' }} || backgroundType !== @js($item?->background_type))">
        <small class="field-help">Images: JPG, PNG, WebP or GIF, up to 4 MB. Videos: MP4 or WebM, up to 20 MB. Videos play muted; changing media type requires a new upload.</small>
    </div>
    <div class="field"><label for="{{ $formId }}-background-color">Background / Fallback Color</label><input id="{{ $formId }}-background-color" type="color" name="background_color" value="{{ $value('background_color', '#0b3b60') }}" required></div>
    <div class="field"><label for="{{ $formId }}-text-color">Text Color</label><input id="{{ $formId }}-text-color" type="color" name="text_color" value="{{ $value('text_color', '#ffffff') }}" required></div>
    <div class="field"><label for="{{ $formId }}-button-label">Button Label (optional)</label><input id="{{ $formId }}-button-label" name="button_label" value="{{ $value('button_label') }}" maxlength="80" placeholder="Explore insights"></div>
    <div class="field"><label for="{{ $formId }}-button-url">Button URL</label><input id="{{ $formId }}-button-url" name="button_url" value="{{ $value('button_url') }}" maxlength="500" placeholder="/articles or https://example.com"><small class="field-help">Use a site path starting with / or a full HTTP(S) URL.</small></div>
    <div class="field field-full"><label class="checkbox-label"><input type="checkbox" name="is_active" value="1" @checked($failed ? old('is_active', false) : ($item?->is_active ?? true))> Active — display this slide on the selected page</label></div>
</div>
