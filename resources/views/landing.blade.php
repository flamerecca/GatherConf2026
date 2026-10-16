@extends('layouts.app')

@section('route', 'landing')
@section('title', __('landing.title'))
@section('description', __('landing.description'))

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
