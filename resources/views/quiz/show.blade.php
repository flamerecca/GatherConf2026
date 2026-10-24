@extends('layouts.app')

@section('route', 'quiz')
@section('robots', 'noindex')
@section('title', __('quiz.show.title'))
@section('description', __('quiz.show.title'))

@section('content')
    @php
        $isEnglish = app()->getLocale() === 'en';
        $total = $questions->count();
    @endphp
    <x-subpage-header :switch-url="$isEnglish ? route('quiz') : route('quiz.en')"
                      :switch-label="__('quiz.switch_label')" :back-label="__('quiz.back')" />
    <main id="main" tabindex="-1" class="mx-auto max-w-2xl px-4 py-12">
        <h1 class="text-4xl font-bold tracking-tight">{{ __('quiz.show.heading') }}</h1>

        @if ($total === 0)
            <p class="mt-8 rounded-2xl border border-ink/15 bg-parchment-dark p-6" data-quiz-empty>{{ __('quiz.show.empty') }}</p>
        @else
            @if ($errors->any())
                <p class="mt-6 rounded-2xl border border-copper p-4 text-sm text-copper" role="alert">{{ __('quiz.show.unanswered') }}</p>
            @endif

            <form method="POST" action="{{ $isEnglish ? route('quiz.store.en') : route('quiz.store') }}" data-quiz class="mt-8">
                @csrf
                <div class="hidden" data-quiz-progress>
                    <p class="text-sm font-semibold" aria-live="polite" data-quiz-status></p>
                    <progress class="mt-2 h-2 w-full accent-moss" max="{{ $total }}" value="1" aria-label="{{ __('quiz.show.progress') }}"></progress>
                </div>

                <div data-quiz-steps>
                @foreach ($questions as $index => $question)
                    <fieldset data-quiz-step aria-describedby="question-{{ $question->id }}" class="mt-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6"
                              data-question-label="{{ __('quiz.show.question', ['current' => $index + 1, 'total' => $total]) }}">
                        <legend class="px-2 text-sm font-semibold text-ink/70">
                            {{ __('quiz.show.question', ['current' => $index + 1, 'total' => $total]) }}
                        </legend>
                        <p id="question-{{ $question->id }}" class="text-xl leading-relaxed font-semibold">{{ $question->text }}</p>
                        <div class="mt-6 grid gap-2 sm:grid-cols-5">
                            @foreach (__('quiz.show.scale') as $value => $label)
                                <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-ink/40 px-3 py-2 text-sm has-[:checked]:border-moss has-[:checked]:bg-moss has-[:checked]:text-on-accent sm:flex-col sm:justify-center sm:text-center">
                                    <input type="radio" name="answers[{{ $question->id }}]" value="{{ $value }}"
                                           class="accent-moss" @checked((string) old('answers.'.$question->id, 3) === (string) $value)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
                </div>

                <div class="mt-8 flex items-center justify-between gap-4">
                    <button type="button" hidden data-quiz-previous
                            class="rounded-full border border-ink/40 px-6 py-3 font-semibold transition hover:bg-ink hover:text-parchment">
                        {{ __('quiz.show.previous') }}
                    </button>
                    <button type="button" hidden data-quiz-next
                            class="ml-auto rounded-full bg-moss px-8 py-3 font-semibold text-on-accent shadow transition hover:bg-ink disabled:opacity-50">
                        {{ __('quiz.show.next') }}
                    </button>
                    <button type="submit" data-quiz-submit
                            class="ml-auto rounded-full bg-moss px-8 py-3 font-semibold text-on-accent shadow transition hover:bg-ink disabled:opacity-50">
                        {{ __('quiz.show.submit') }}
                    </button>
                </div>
            </form>
        @endif
    </main>
    @include('landing.footer')
@endsection
