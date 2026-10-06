@php
    $failed = old('_form') === $formId;
    $value = fn ($key, $default = null) => $failed ? old($key, $default) : ($item?->{$key} ?? $default);
@endphp
<input type="hidden" name="_form" value="{{ $formId }}">
<div class="field"><label for="{{ $formId }}-name">Category Name</label><input id="{{ $formId }}-name" name="name" value="{{ $value('name') }}" maxlength="120" required></div>
<div class="field"><label for="{{ $formId }}-description">Description</label><textarea id="{{ $formId }}-description" name="description" rows="3">{{ $value('description') }}</textarea></div>
<div x-data="{ displayType: @js($value('display_type', 'number')) }">
    <div class="field"><label for="{{ $formId }}-display">Frontend Display</label><select id="{{ $formId }}-display" name="display_type" x-model="displayType"><option value="number">Number</option><option value="icon">Font Awesome Icon</option><option value="image">Featured Image</option></select></div>
    <div class="field" x-show="displayType === 'number'"><label for="{{ $formId }}-number">Display Number</label><input id="{{ $formId }}-number" type="number" name="display_number" value="{{ $value('display_number') }}" min="0" max="999999"><small class="field-help">Leave blank to use the category's sort order, or its position when no order is set.</small></div>
    <div class="field" x-show="displayType === 'icon'"><label for="{{ $formId }}-icon">Icon Classes</label><input id="{{ $formId }}-icon" name="icon" value="{{ $value('icon') }}" placeholder="fas fa-chart-line" maxlength="100" :required="displayType === 'icon'"><small class="field-help">Examples: fas fa-chart-line, fas fa-coins, fa-solid fa-microchip.</small></div>
    <div class="field" x-show="displayType === 'image'">
        <label for="{{ $formId }}-image">Featured Image</label>
        @if($item?->featured_image)<img class="media-preview" src="{{ asset('storage/'.$item->featured_image) }}" alt="Current category image">@endif
        <input id="{{ $formId }}-image" type="file" name="featured_image" accept="image/jpeg,image/png,image/webp,image/gif" :required="displayType === 'image' && {{ $item?->featured_image ? 'false' : 'true' }}">
        <small class="field-help">JPG, PNG, WebP or GIF, maximum 4 MB. Leave empty to keep the current image.</small>
    </div>
</div>
<div class="field"><label for="{{ $formId }}-order">Sort Order</label><input id="{{ $formId }}-order" type="number" name="sort_order" value="{{ $value('sort_order', 0) }}" min="0"></div>
<div class="checkbox-row">
    <label class="checkbox-label"><input type="checkbox" name="is_featured" value="1" @checked($value('is_featured', false))> Featured</label>
    <label class="checkbox-label"><input type="checkbox" name="is_active" value="1" @checked($failed ? old('is_active', false) : ($item?->is_active ?? true))> Active</label>
</div>
