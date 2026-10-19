@extends('layouts.app')

@section('route', 'bbti')
@section('title', __('quiz.intro.title'))
@section('description', __('quiz.intro.description', ['count' => $count]))

@section('content')
    @php
        $isEnglish = app()->getLocale() === 'en';
    @endphp
    <x-subpage-header :switch-url="$isEnglish ? route('bbti') : route('bbti.en')"
                      :switch-label="__('quiz.switch_label')" :back-label="__('quiz.back')" />
    <main id="main" tabindex="-1" class="mx-auto max-w-2xl px-4 py-12">
        <h1 class="text-4xl font-bold tracking-tight md:text-5xl">{{ __('quiz.intro.heading') }}</h1>
        <p class="mt-2 text-lg font-semibold text-moss">{{ __('quiz.intro.subheading') }}</p>
        <p class="mt-6 text-lg leading-relaxed text-ink/80">{{ __('quiz.intro.body') }}</p>
        <p class="mt-4 leading-relaxed text-ink/80">{{ __('quiz.intro.how', ['count' => $count, 'minutes' => $minutes]) }}</p>
        <p class="mt-2 text-sm text-ink/70">
            {{ __('quiz.intro.reward', ['count' => config('carrots.site.complete_quiz')]) }}
            @guest('member')
                {{ __('quiz.intro.login_hint') }}
            @endguest
        </p>
        <a href="{{ $isEnglish ? route('quiz.en') : route('quiz') }}" data-start-quiz
           class="mt-8 inline-flex rounded-full bg-moss px-8 py-3 font-semibold text-on-accent shadow transition hover:bg-ink">
            {{ __('quiz.intro.start') }}
        </a>
    </main>
    @include('landing.footer')
@endsection
