<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $document->seo_title ?: ($channel->home_seo['meta_title'] ?? $channel->name) }}</title>

    <meta name="description" content="{{ $document->seo_description ?: ($channel->home_seo['meta_description'] ?? '') }}">

    @if ($channel->logo)
        <link rel="icon" href="{{ Storage::url($channel->logo) }}">
    @endif

    {{-- CSS خود موتور صفحه‌ساز + پایهٔ ظاهری ما (`elementor.extra_styles`). --}}
    {!! \Modules\Elementor\Support\Assets::styleTags() !!}

    {{-- CSS تولیدشدهٔ خود سند و بخش‌های هدر/فوتر. --}}
    @foreach ($css_links ?? [] as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach

    <link rel="stylesheet" href="{{ asset('pb/site.css') }}">

    @vite(['Modules/Elementor/resources/js/front.js'])
</head>

{{--
    هدر و فوتر هم اسناد صفحه‌ساز‌ند، نه ویوی تم.

    برای همین این صفحه داخل `<x-shop::layouts>` نمی‌نشیند: آن قالب هدر و فوتر
    خودِ بجیستو را می‌آورد و آن‌وقت دو هدر روی هم می‌افتاد. `SiteParts` همان
    مکانیزمی است که هستهٔ صفحه‌ساز برای این کار دارد و بلاگ هم از آن استفاده
    می‌کند.

    میان‌افزار `shop` همچنان روی روت هست، پس کانال، زبان، ارز و سبد خرید در
    دسترس‌اند و ویجت‌های فروشگاهی (سبد، جست‌وجو) کار می‌کنند.
--}}
<body style="margin:0" class="{{ \Modules\Elementor\Support\SiteParts::bodyClasses($document) }}">

@if ($showAdminBar)
    @include('Elementor::parts.admin-bar', [
        'barDocumentId' => $document->id,
        'barDocument'   => $document,
    ])
@endif

{!! $header_html ?? '' !!}

{!! $html !!}

{!! $footer_html ?? '' !!}

@if ($document->custom_js)
    <script>{!! $document->custom_js !!}</script>
@endif

</body>
</html>
