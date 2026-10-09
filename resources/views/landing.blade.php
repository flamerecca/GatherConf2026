<!DOCTYPE html>
<html lang="{{ __('landing.html_lang') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('landing.title') }}</title>
        <meta name="description" content="{{ __('landing.description') }}">
        <link rel="icon" href="{{ Vite::asset('resources/images/GatherConf-Logo.jpeg') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-parchment text-ink antialiased">
        @include('landing.hero')
        @include('landing.mission')
        @include('landing.overview')
        @include('landing.sponsorship')
        @include('landing.footer')
    </body>
</html>
