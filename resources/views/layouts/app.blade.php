<!DOCTYPE html>
<html lang="{{ __('landing.html_lang') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <title>@yield('title')</title>
        <meta name="description" content="@yield('description')">
        <script>
            (function () {
                try {
                    var saved = localStorage.getItem('theme');
                    var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
                } catch (e) {}
            })();
        </script>
        <link rel="icon" href="{{ Vite::asset('resources/images/GatherConf-Logo.png') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-parchment text-ink antialiased">
        @yield('content')
    </body>
</html>
