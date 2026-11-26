@extends('layouts.app')

@section('route', 'home')
@section('title', __('home.title'))
@section('description', __('home.description'))

@section('content')
    @php
        $isEnglish = app()->getLocale() === 'en';
        $email = 'contact@mg.gatherconf.net';
    @endphp
    <header class="mx-auto max-w-6xl px-4 pt-6">
        <nav class="flex items-center justify-end gap-4">
            <x-member-button />
            <x-theme-toggle />
            <a href="{{ $isEnglish ? route('home') : route('home.en') }}"
               class="rounded-full border border-ink/30 px-4 py-2 text-sm font-medium transition hover:bg-ink hover:text-parchment">
                {{ __('landing.switch_label') }}
            </a>
        </nav>
    </header>
    <main id="main" tabindex="-1" class="mx-auto flex max-w-3xl flex-col items-center px-4 py-12 text-center md:py-20">
        <x-responsive-image name="brand/GatherConf-Logo" :alt="__('landing.logo_alt')" :width="687" :height="687" :widths="[128, 256, 448, 687]"
                            sizes="(min-width: 480px) 288px, 70vw" loading="eager" fetchpriority="high"
                            class="w-[70vw] max-w-72 rounded-full mix-blend-multiply dark:mix-blend-normal" />
        <p class="mt-10 text-sm font-semibold tracking-widest text-copper uppercase">{{ __('home.eyebrow') }}</p>
        <h1 class="mt-4 text-4xl font-bold tracking-tight md:text-5xl">{{ __('home.heading') }}</h1>
        <p class="mt-6 text-lg leading-relaxed text-ink/80">{{ __('home.body') }}</p>
        <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ $isEnglish ? route('landing.en') : route('landing') }}" data-home-review
               class="inline-block rounded-full bg-moss px-8 py-4 text-lg font-semibold text-on-accent shadow transition hover:bg-ink">
                {{ __('home.review_cta') }}
            </a>
            <a href="{{ $isEnglish ? route('bbti.en') : route('bbti') }}" data-home-bbti
               class="inline-block rounded-full border-2 border-moss px-8 py-4 text-lg font-semibold text-moss transition hover:bg-moss hover:text-on-accent">
                {{ __('landing.bbti_cta') }}
            </a>
        </div>
        <p class="mt-10 text-ink/80">{{ __('home.contact') }}
            <a href="mailto:{{ $email }}" class="font-medium text-moss underline underline-offset-4">{{ $email }}</a>
        </p>
    </main>
    <footer class="bg-deep py-8 text-center text-sm text-on-deep/80">
        <p class="font-semibold text-on-deep">{{ __('landing.name') }} GatherConf</p>
        <div class="mt-3 flex flex-wrap justify-center gap-x-6 gap-y-2">
            <a href="{{ $isEnglish ? route('volunteers.en') : route('volunteers') }}" class="inline-flex min-h-6 items-center text-gold underline">{{ __('home.volunteers_cta') }}</a>
            <a href="{{ $isEnglish ? route('conduct.en') : route('conduct') }}" class="inline-flex min-h-6 items-center text-gold underline">{{ __('landing.conduct_link') }}</a>
        </div>
    </footer>
@endsection
