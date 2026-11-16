<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Canvas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    @vite(['resources/css/frontend.css', 'resources/css/builder-canvas.css', 'resources/js/builder-frontend.js'])
    @include('frontend.articles.partials.body-styles')
    <style id="ffb-css"></style>
</head>
<body class="ffbc">
<article class="article-wrap article-wrap--default" id="ffbc-wrap">
    <header class="article-header" id="ffbc-header" title="Click to edit the title, excerpt and featured image">
        <div class="eyebrow" id="ffbc-category"></div>
        <h1 id="ffbc-title"></h1>
        <p class="article-excerpt" id="ffbc-excerpt" hidden></p>
        <div class="article-meta">{{ auth()->user()->name }} · {{ now()->format('d M Y') }} · reading time is calculated on save</div>
        <img class="article-featured" id="ffbc-featured" alt="" hidden>
        <span class="ffbc-header-hint"><i class="fa-solid fa-sliders"></i> Article settings</span>
    </header>
    <div class="article-body article-body--builder" id="ffb-root"></div>
    <div id="ffb-add-zone" class="ffbc-add-zone">
        <button type="button" data-zone-action="section" title="Add a new section"><i class="fa-solid fa-plus"></i></button>
        <button type="button" data-zone-action="template" title="Insert a block or saved template"><i class="fa-solid fa-folder-open"></i></button>
        <span>Drag a widget here, add a section or insert a template</span>
    </div>
</article>
</body>
</html>
