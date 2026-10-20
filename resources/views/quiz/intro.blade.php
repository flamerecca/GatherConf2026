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
        @if ($lastAttempt?->personalityType)
            <div data-last-result="{{ $lastAttempt->type_code }}"
                 class="mt-8 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
                <p class="text-sm font-semibold text-ink/70">{{ __('quiz.last.heading') }}</p>
                <p class="mt-2 text-2xl font-bold">
                    {{ $lastAttempt->type_code }}
                    <span class="text-moss">{{ $lastAttempt->personalityType->name }}</span>
                </p>
                <p class="mt-1 text-sm text-ink/70">{{ __('quiz.last.completed_at', ['date' => $lastAttempt->completed_at->format('Y-m-d')]) }}</p>
                <a href="{{ $isEnglish ? route('quiz.result.en', $lastAttempt) : route('quiz.result', $lastAttempt) }}"
                   class="mt-4 inline-flex font-semibold text-moss underline underline-offset-4">{{ __('quiz.last.view') }}</a>
            </div>
        @endif
        <a href="{{ $isEnglish ? route('quiz.en') : route('quiz') }}" data-start-quiz
           class="mt-8 inline-flex rounded-full bg-moss px-8 py-3 font-semibold text-on-accent shadow transition hover:bg-ink">
            {{ $lastAttempt ? __('quiz.last.retake') : __('quiz.intro.start') }}
        </a>
    </main>
    @include('landing.footer')
@endsection
