<!DOCTYPE html>
@php
    $routeBase = trim($__env->yieldContent('route'));
    $isEnglish = app()->getLocale() === 'en';
    $zhUrl = trim($__env->yieldContent('zh_url')) ?: route($routeBase);
    $enUrl = trim($__env->yieldContent('en_url')) ?: route($routeBase.'.en');
    $canonicalUrl = $isEnglish ? $enUrl : $zhUrl;
    $ogImage = asset('og-image.jpg');
@endphp
<html lang="{{ __('landing.html_lang') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <title>@yield('title')</title>
        <meta name="description" content="@yield('description')">
        @hasSection('robots')
            <meta name="robots" content="@yield('robots')">
        @endif
        <meta name="theme-color" content="#f4efe1" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#121c15" media="(prefers-color-scheme: dark)">
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <link rel="alternate" hreflang="zh-Hant-TW" href="{{ $zhUrl }}">
        <link rel="alternate" hreflang="en" href="{{ $enUrl }}">
        <link rel="alternate" hreflang="x-default" href="{{ $zhUrl }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="GatherConf">
        <meta property="og:title" content="@yield('title')">
        <meta property="og:description" content="@yield('description')">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:locale" content="{{ $isEnglish ? 'en_US' : 'zh_TW' }}">
        <meta property="og:locale:alternate" content="{{ $isEnglish ? 'zh_TW' : 'en_US' }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ __('landing.gathering_alt') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="@yield('title')">
        <meta name="twitter:description" content="@yield('description')">
        <meta name="twitter:image" content="{{ $ogImage }}">
        <script>
            (function () {
                try {
                    var saved = localStorage.getItem('theme');
                    var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
                } catch (e) {}
            })();
        </script>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
        <link rel="icon" href="{{ asset('favicon-32x32.png') }}" sizes="32x32" type="image/png">
        <link rel="icon" href="{{ asset('favicon-16x16.png') }}" sizes="16x16" type="image/png">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        @stack('preload')
        @stack('structured-data')
        @if (Vite::isRunningHot())
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>{!! Vite::content('resources/css/app.css') !!}</style>
            @vite('resources/js/app.js')
        @endif
    </head>
    <body class="bg-parchment text-ink antialiased">
        <a href="#main"
           class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-full focus:bg-moss focus:px-6 focus:py-3 focus:font-semibold focus:text-on-accent">
            {{ __('landing.skip_to_content') }}
        </a>
        @yield('content')
    </body>
</html>
