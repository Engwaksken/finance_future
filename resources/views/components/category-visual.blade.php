@props(['category', 'fallback' => 1])
@if($category->display_type === 'image' && $category->featured_image)
    <img {{ $attributes->class('category-visual category-image') }} src="{{ asset('storage/'.$category->featured_image) }}" alt="{{ $category->name }}" loading="lazy">
@elseif($category->display_type === 'icon' && $category->icon)
    <span {{ $attributes->class('category-visual category-icon') }}><i class="{{ $category->icon }}" aria-hidden="true"></i></span>
@else
    <span {{ $attributes->class('category-visual category-number') }}>{{ str_pad($category->display_number ?? ($category->sort_order ?: $fallback), 2, '0', STR_PAD_LEFT) }}</span>
@endif
