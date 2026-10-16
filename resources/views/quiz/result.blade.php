@extends('layouts.app')

@section('route', 'quiz.result')
@section('zh_url', route('quiz.result', $attempt))
@section('en_url', route('quiz.result.en', $attempt))
@section('robots', 'noindex')
@section('title', __('quiz.result.title', ['code' => $type->code, 'name' => $type->name]))
@section('description', __('quiz.result.description', ['code' => $type->code, 'name' => $type->name, 'summary' => $type->summary]))

@section('content')
    @php
        $isEnglish = app()->getLocale() === 'en';
    @endphp
    <x-subpage-header :switch-url="$isEnglish ? route('quiz.result', $attempt) : route('quiz.result.en', $attempt)"
                      :switch-label="__('quiz.switch_label')" :back-label="__('quiz.back')" />
    <main id="main" tabindex="-1" class="mx-auto max-w-2xl px-4 py-12">
        <p class="text-sm font-semibold tracking-widest text-copper uppercase">{{ __('quiz.result.your_type') }}</p>
        <h1 class="mt-2 text-5xl font-bold tracking-tight" data-type-code="{{ $type->code }}">
            {{ $type->code }}
            <span class="block text-3xl text-moss">{{ $type->name }}</span>
        </h1>
        <p class="mt-6 text-lg leading-relaxed text-ink/80">{{ $type->summary }}</p>

        <section class="mt-10" aria-labelledby="axes-heading">
            <h2 id="axes-heading" class="text-xl font-bold text-moss">{{ __('quiz.result.axes') }}</h2>
            <div class="mt-4 space-y-6">
                @foreach ($bars as $bar)
                    <div data-axis="{{ $bar['axis'] }}" data-percent="{{ $bar['percent'] }}" data-leaning="{{ $bar['leaning'] }}">
                        <p class="text-sm font-semibold">{{ __('quiz.result.axis.'.$bar['axis']) }}</p>
                        <div class="mt-2 flex justify-between text-sm">
                            <span @class(['font-bold' => $bar['leaning'] === $bar['positive']])>{{ $bar['positive'] }} {{ __('quiz.result.pole.'.$bar['positive']) }}</span>
                            <span @class(['font-bold' => $bar['leaning'] === $bar['negative']])>{{ __('quiz.result.pole.'.$bar['negative']) }} {{ $bar['negative'] }}</span>
                        </div>
                        <div class="relative mt-1 h-4 rounded-full bg-parchment-dark" role="img"
                             aria-label="{{ __('quiz.result.leaning', ['pole' => $bar['leaning'].' '.__('quiz.result.pole.'.$bar['leaning']), 'percent' => $bar['percent']]) }}">
                            <div class="absolute inset-y-0 left-1/2 w-px bg-ink/40"></div>
                            <div @class(['absolute inset-y-0 rounded-full', 'bg-axis-nm' => $bar['axis'] === 'NM', 'bg-axis-sv' => $bar['axis'] === 'SV', 'bg-axis-ha' => $bar['axis'] === 'HA', 'bg-axis-gd' => $bar['axis'] === 'GD'])
                                 style="width: {{ $bar['percent'] / 2 }}%; {{ $bar['leaning'] === $bar['positive'] ? 'left: 50%' : 'right: 50%' }}"></div>
                        </div>
                        <p class="mt-1 text-sm text-ink/70">{{ __('quiz.result.leaning', ['pole' => $bar['leaning'], 'percent' => $bar['percent']]) }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <p class="mt-10 leading-relaxed">{{ $type->description }}</p>

        <div class="mt-10 grid gap-6 sm:grid-cols-2">
            @foreach (['strengths', 'weaknesses'] as $list)
                @if (! empty($type->$list))
                    <section aria-labelledby="{{ $list }}-heading">
                        <h2 id="{{ $list }}-heading" class="text-xl font-bold text-moss">{{ __('quiz.result.'.$list) }}</h2>
                        <ul class="mt-3 list-disc space-y-2 pl-5">
                            @foreach ($type->$list as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach
        </div>

        <div class="mt-12 flex flex-wrap items-center gap-4">
            <button type="button" data-copy-link="{{ url()->current() }}" data-copied-label="{{ __('quiz.result.copied') }}"
                    class="rounded-full bg-moss px-8 py-3 font-semibold text-on-accent shadow transition hover:bg-ink">
                {{ __('quiz.result.copy') }}
            </button>
            <a href="{{ $isEnglish ? route('quiz.en') : route('quiz') }}"
               class="rounded-full border border-ink/40 px-6 py-3 font-semibold transition hover:bg-ink hover:text-parchment">
                {{ __('quiz.result.retake') }}
            </a>
            <span class="sr-only" role="status" data-copy-status></span>
        </div>
    </main>
    @include('landing.footer')
@endsection
