@extends('layouts.app')

@section('title', __('conduct.title'))
@section('description', __('conduct.description'))

@section('content')
    @php
        $contactEmail = 'contact@mg.gatherconf.net';
        $contactLink = '<a href="mailto:'.e($contactEmail).'" class="font-medium text-moss underline">'.e($contactEmail).'</a>';
    @endphp
    <header class="mx-auto flex max-w-3xl items-center justify-between px-4 pt-6">
        <a href="{{ app()->getLocale() === 'en' ? route('landing.en') : route('landing') }}" class="flex items-center gap-3 font-semibold text-moss hover:underline">
            <img src="{{ Vite::asset('resources/images/GatherConf-Logo.png') }}" alt="{{ __('landing.logo_alt') }}" class="size-10 rounded-full">
            {{ __('conduct.back') }}
        </a>
        <a href="{{ app()->getLocale() === 'en' ? route('conduct') : route('conduct.en') }}"
           class="rounded-full border border-ink/30 px-4 py-1 text-sm font-medium transition hover:bg-ink hover:text-parchment">
            {{ __('conduct.switch_label') }}
        </a>
    </header>
    <main class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="text-4xl font-bold tracking-tight md:text-5xl">{{ __('conduct.heading') }}</h1>
        <p class="mt-6 text-lg leading-relaxed text-ink/80">{{ __('conduct.intro') }}</p>
        @foreach (__('conduct.sections') as $section)
            <section class="mt-10">
                <h2 class="text-2xl font-bold text-moss">{{ $section['heading'] }}</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($section['items'] as $item)
                        <li class="flex gap-3 leading-relaxed">
                            <span class="text-copper" aria-hidden="true">✦</span>
                            <span>{!! str_replace(':email', $contactLink, e($item)) !!}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
        <p class="mt-12 border-l-4 border-gold pl-4 text-lg">{{ __('conduct.closing') }}</p>
    </main>
    @include('landing.footer')
@endsection
