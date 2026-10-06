document.querySelectorAll('[data-page-slider]').forEach((slider) => {
    const slides = [...slider.querySelectorAll('[data-slide]')];
    const dots = [...slider.querySelectorAll('[data-slide-to]')];
    const pauseButton = slider.querySelector('[data-slide-pause]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0;
    let paused = reducedMotion.matches;
    let hovered = false;
    let inView = true;

    const syncVideos = () => {
        slides.forEach((slide, position) => {
            const video = slide.querySelector('video');
            if (!video) return;
            video.muted = true;
            if (position === index && !paused && !document.hidden && inView) {
                video.play().catch(() => {});
            } else {
                video.pause();
            }
        });
    };
    const showSlide = (nextIndex) => {
        index = (nextIndex + slides.length) % slides.length;
        slides.forEach((slide, position) => { slide.hidden = position !== index; });
        dots.forEach((dot, position) => { dot.setAttribute('aria-pressed', String(position === index)); });
        syncVideos();
    };
    const syncPauseButton = () => {
        if (!pauseButton) return;
        pauseButton.textContent = paused ? 'Play motion' : 'Pause motion';
        pauseButton.setAttribute('aria-label', paused ? 'Play slide motion' : 'Pause slide motion');
    };

    const controls = slider.querySelector('[data-slider-controls]');
    if (controls) controls.hidden = false;
    slider.querySelector('[data-slide-prev]')?.addEventListener('click', () => showSlide(index - 1));
    slider.querySelector('[data-slide-next]')?.addEventListener('click', () => showSlide(index + 1));
    dots.forEach((dot) => dot.addEventListener('click', () => showSlide(Number(dot.dataset.slideTo))));
    pauseButton?.addEventListener('click', () => {
        paused = !paused;
        syncPauseButton();
        syncVideos();
    });
    slider.addEventListener('mouseenter', () => { hovered = true; });
    slider.addEventListener('mouseleave', () => { hovered = false; });
    slider.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            showSlide(index + (event.key === 'ArrowRight' ? 1 : -1));
        }
    });
    document.addEventListener('visibilitychange', syncVideos);
    reducedMotion.addEventListener('change', () => {
        paused = reducedMotion.matches;
        syncPauseButton();
        syncVideos();
    });
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            inView = entry.isIntersecting;
            syncVideos();
        }).observe(slider);
    }
    if (slides.length > 1) {
        window.setInterval(() => {
            if (!paused && !hovered && !slider.contains(document.activeElement) && !document.hidden && inView) {
                showSlide(index + 1);
            }
        }, 7000);
    }
    syncPauseButton();
    syncVideos();
});
