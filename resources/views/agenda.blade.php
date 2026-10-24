@extends('layouts.app')

@section('route', 'agenda')
@section('title', __('agenda.title'))
@section('description', __('agenda.description'))

@section('content')
    @php
        $locale = app()->getLocale();
    @endphp
    <x-subpage-header :switch-url="$locale === 'en' ? route('agenda') : route('agenda.en')"
                      :switch-label="__('agenda.switch_label')" :back-label="__('agenda.back')" />
    <main id="main" tabindex="-1" class="mx-auto max-w-6xl px-4 py-12">
        <h1 class="text-4xl font-bold tracking-tight md:text-5xl">{{ __('agenda.heading') }}</h1>
        <p class="mt-6 max-w-3xl text-lg leading-relaxed text-ink/80">{{ __('agenda.intro') }}</p>
        @foreach (config('agenda') as $track)
            <section class="mt-12" aria-labelledby="track-{{ $track['id'] }}">
                <h2 id="track-{{ $track['id'] }}" class="text-2xl font-bold text-moss">
                    {{ __('agenda.track', ['name' => strtoupper($track['id'])]) }}
                </h2>
                <ol class="mt-6 grid gap-6">
                    @foreach ($track['sessions'] as $index => $session)
                        @php
                            $title = $session['title'][$locale] ?? null;
                            $outline = $session['outline'][$locale] ?? null;
                            $speaker = $session['speaker_name'];
                            $bio = $session['speaker_bio'][$locale] ?? null;
                        @endphp
                        <li id="session-{{ $session['id'] }}" data-session="{{ $session['id'] }}"
                            class="flex h-full flex-col gap-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
                            <p class="text-sm font-semibold tracking-widest text-copper uppercase">
                                {{ __('agenda.session', ['number' => $index + 1]) }}
                                <span aria-hidden="true">·</span>
                                <time>{{ $session['date'] }} {{ $session['time'] }}</time>
                            </p>
                            <div class="flex items-center gap-4">
                                @if ($session['speaker_photo'])
                                    <img src="{{ Vite::asset('resources/images/speakers/'.$session['speaker_photo']) }}"
                                         alt="{{ __('agenda.speaker_photo_alt', ['name' => $speaker ?? '']) }}"
                                         class="size-20 shrink-0 rounded-full object-cover">
                                @else
                                    <div class="flex size-20 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-ink/60 text-ink/70"
                                         role="img" aria-label="{{ __('agenda.speaker_placeholder') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-8" aria-hidden="true">
                                            <circle cx="12" cy="8" r="4"/>
                                            <path d="M4 21a8 8 0 0 1 16 0"/>
                                        </svg>
                                    </div>
                                @endif
                                <p class="font-semibold">{{ $speaker ?? __('agenda.speaker_tbd') }}</p>
                            </div>
                            @if ($bio)
                                <p class="text-sm leading-relaxed text-ink/80">{{ $bio }}</p>
                            @endif
                            <h3 @class(['text-xl font-bold', 'text-ink/70' => ! $title])>{{ $title ?? __('agenda.title_tbd') }}</h3>
                            <div>
                                <p class="text-sm font-semibold">{{ __('agenda.outline_label') }}</p>
                                <p @class(['mt-1 text-sm leading-relaxed', 'text-ink/80' => $outline, 'text-ink/70' => ! $outline])>{{ $outline ?? __('agenda.outline_tbd') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </main>
    @include('landing.footer')
@endsection
