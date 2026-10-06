@props(['title', 'description' => null, 'image' => null, 'type' => 'website', 'canonical' => null])
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $title }}">
@if (filled($description))
    <meta property="og:description" content="{{ $description }}">
@endif
@if (filled($image))
    <meta property="og:image" content="{{ $image }}">
@endif
@if (filled($canonical))
    <link rel="canonical" href="{{ $canonical }}">
@endif
