@if($pageSlides->isNotEmpty())
<section class="page-slider" data-page-slider aria-label="Page highlights" aria-roledescription="carousel">
    @foreach($pageSlides as $slide)
        <div class="page-slide" data-slide role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $pageSlides->count() }}" style="background-color:{{ $slide->background_color }};color:{{ $slide->text_color }}" @if(!$loop->first) hidden @endif>
            @if($slide->background_type === 'image' && $slide->media_path)
                <img class="page-slide-background" src="{{ asset('storage/'.$slide->media_path) }}" alt="" @if(!$loop->first) loading="lazy" @else fetchpriority="high" @endif>
            @elseif($slide->background_type === 'video' && $slide->media_path)
                <video class="page-slide-background" src="{{ asset('storage/'.$slide->media_path) }}" muted loop playsinline preload="metadata" aria-hidden="true" tabindex="-1"></video>
            @endif
            @if($slide->background_type !== 'color')<div class="page-slide-overlay"></div>@endif
            <div class="container page-slide-content">
                <h2>{{ $slide->title }}</h2>
                @if($slide->description)<p>{{ $slide->description }}</p>@endif
                @if($slide->button_label && $slide->button_url)<a class="btn slide-button" href="{{ $slide->button_url }}">{{ $slide->button_label }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>@endif
            </div>
        </div>
    @endforeach
    @if($pageSlides->count() > 1 || $pageSlides->contains('background_type', 'video'))
        <div class="page-slider-controls" data-slider-controls hidden>
            @if($pageSlides->count() > 1)
                <button type="button" data-slide-prev aria-label="Previous slide"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                <div class="page-slider-dots" aria-label="Choose a slide">
                    @foreach($pageSlides as $slide)<button type="button" data-slide-to="{{ $loop->index }}" aria-label="Show slide {{ $loop->iteration }}: {{ $slide->title }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"></button>@endforeach
                </div>
                <button type="button" data-slide-next aria-label="Next slide"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
            @endif
            <button type="button" data-slide-pause aria-label="Pause slide motion">Pause motion</button>
        </div>
    @endif
</section>
@endif
