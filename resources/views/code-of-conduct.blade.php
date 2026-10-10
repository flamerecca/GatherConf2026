@extends('layouts.app')

@section('title', __('conduct.title'))
@section('description', __('conduct.description'))

@section('content')
    @php
        $contactEmail = 'contact@mg.gatherconf.net';
        $contactLink = '<a href="mailto:'.e($contactEmail).'" class="font-medium text-moss underline">'.e($contactEmail).'</a>';
    @endphp
    <x-subpage-header :switch-url="app()->getLocale() === 'en' ? route('conduct') : route('conduct.en')"
                      :switch-label="__('conduct.switch_label')" :back-label="__('conduct.back')" />
    <main class="mx-auto max-w-3xl px-4 py-12">
        <h1 class="text-4xl font-bold tracking-tight md:text-5xl">{{ __('conduct.heading') }}</h1>
        <p class="mt-6 text-lg leading-relaxed text-ink/80">{{ __('conduct.intro') }}</p>
        @foreach (__('conduct.sections') as $section)
            <section class="mt-12">
                <h2 class="text-2xl font-bold text-moss">{{ $section['heading'] }}</h2>
                <ul class="mt-4 space-y-4">
                    @foreach ($section['items'] as $item)
                        <li class="flex gap-4 leading-relaxed">
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
