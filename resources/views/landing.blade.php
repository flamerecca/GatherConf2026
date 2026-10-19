@extends('layouts.app')

@section('route', 'landing')
@section('title', __('landing.title'))
@section('description', __('landing.description'))

{{-- On wide screens the gathering illustration is the largest paint in the first view, so fetch it early. --}}
@push('preload')
    <link rel="preload" as="image" type="image/avif" media="(min-width: 1024px)" fetchpriority="high"
          imagesizes="(min-width: 1184px) 1120px, calc(100vw - 32px)"
          imagesrcset="{{ collect([360, 480, 576, 672, 768, 864, 960, 1024])->map(fn (int $w) => Vite::asset('resources/images/community-gathering-watercolor-'.$w.'.avif').' '.$w.'w')->implode(', ') }}">
@endpush

@push('structured-data')
    @include('landing.structured-data')
@endpush

@section('content')
        @include('landing.header')
        <main id="main" tabindex="-1">
            @include('landing.hero')
            @include('landing.mission')
            @include('landing.why')
            @include('landing.overview')
            @include('landing.agenda')
            @include('landing.communities')
            @include('landing.sponsorship')
            @include('landing.sponsors')
        </main>
        @include('landing.footer')
@endsection
