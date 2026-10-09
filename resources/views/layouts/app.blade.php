<!DOCTYPE html>
<html lang="{{ __('landing.html_lang') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title')</title>
        <meta name="description" content="@yield('description')">
        <link rel="icon" href="{{ Vite::asset('resources/images/GatherConf-Logo.png') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-parchment text-ink antialiased">
        @yield('content')
    </body>
</html>
