@extends('layouts.app')

@section('title', __('communities.title'))
@section('description', __('communities.description'))

@section('content')
    <x-subpage-header :switch-url="app()->getLocale() === 'en' ? route('communities') : route('communities.en')"
                      :switch-label="__('communities.switch_label')" :back-label="__('communities.back')" />
    <main class="mx-auto max-w-6xl px-4 py-12">
        <h1 class="text-4xl font-bold tracking-tight md:text-5xl">{{ __('communities.heading') }}</h1>
        <p class="mt-6 max-w-3xl text-lg leading-relaxed text-ink/80">{{ __('communities.intro') }}</p>
        <ul class="mt-12 grid gap-6 sm:grid-cols-2 md:grid-cols-3">
            @foreach (config('communities') as $community)
                <li>
                    <x-community-card :community="$community" />
                </li>
            @endforeach
        </ul>
    </main>
    @include('landing.footer')
@endsection
