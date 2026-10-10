@extends('layouts.app')

@section('title', __('landing.title'))
@section('description', __('landing.description'))

@section('content')
        @include('landing.hero')
        @include('landing.mission')
        @include('landing.why')
        @include('landing.overview')
        @include('landing.communities')
        @include('landing.sponsorship')
        @include('landing.sponsors')
        @include('landing.footer')
@endsection
