@extends('layouts.app')

@section('route', 'volunteers')
@section('title', __('volunteers.title'))
@section('description', __('volunteers.description'))

@section('content')
    @php
        $locale = app()->getLocale();
        $teams = collect(config('volunteers'))->filter(fn (array $team) => $team['members'] !== []);
    @endphp
    <x-subpage-header :switch-url="$locale === 'en' ? route('volunteers') : route('volunteers.en')"
                      :switch-label="__('volunteers.switch_label')" :back-label="__('volunteers.back')" />
    <main id="main" tabindex="-1" class="mx-auto max-w-6xl px-4 py-12">
        <h1 class="text-4xl font-bold tracking-tight md:text-5xl">{{ __('volunteers.heading') }}</h1>
        <p class="mt-6 max-w-3xl text-lg leading-relaxed text-ink/80">{{ __('volunteers.intro') }}</p>
        @if ($teams->isEmpty())
            <p class="mt-12 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6 text-ink/80">{{ __('volunteers.empty') }}</p>
        @else
            @foreach ($teams as $team)
                <section class="mt-12" aria-labelledby="team-{{ $loop->index }}">
                    <h2 id="team-{{ $loop->index }}" class="text-2xl font-bold text-moss">{{ $team['name'][$locale] }}</h2>
                    <ul class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-4 md:grid-cols-6">
                        @foreach ($team['members'] as $member)
                            <li class="flex flex-col items-center gap-3 text-center">
                                <img src="{{ Vite::asset('resources/images/volunteers/'.($member['photo'] ?? 'default-bunny.svg')) }}"
                                     alt="{{ __('volunteers.photo_alt', ['name' => $member['name']]) }}"
                                     loading="lazy" decoding="async"
                                     class="aspect-square w-full max-w-32 rounded-full object-cover object-top">
                                <span class="font-medium">{{ $member['name'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        @endif
    </main>
    @include('landing.footer')
@endsection
