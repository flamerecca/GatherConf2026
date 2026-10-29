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
            @php
                $isTrackB = $track['id'] === 'b';
            @endphp
            <section class="mt-12" aria-labelledby="track-{{ $track['id'] }}">
                <h2 id="track-{{ $track['id'] }}" @class(['text-2xl font-bold', 'text-moss' => ! $isTrackB, 'text-copper' => $isTrackB])>
                    {{ __('agenda.track', ['name' => strtoupper($track['id'])]) }}
                </h2>
                <ol class="mt-6 grid gap-6">
                    @foreach ($track['sessions'] as $index => $session)
                        @php
                            $talks = $session['talks'] ?? [$session];
                        @endphp
                        <li id="session-{{ $session['id'] }}" data-session="{{ $session['id'] }}"
                            @class(['flex h-full flex-col gap-6 rounded-2xl border-4 bg-parchment-dark p-6', 'border-moss/60' => ! $isTrackB, 'border-copper/60' => $isTrackB])>
                            <p class="text-sm font-semibold tracking-widest text-copper uppercase">
                                {{ __('agenda.session', ['number' => $index + 1]) }}
                                <span aria-hidden="true">·</span>
                                <time>{{ $session['date'] }} {{ $session['time'] }}</time>
                            </p>
                            @foreach ($talks as $talkIndex => $talk)
                                @php
                                    $title = $talk['title'][$locale] ?? null;
                                    $outline = $talk['outline'][$locale] ?? null;
                                    $speaker = $talk['speaker_name'];
                                    $bio = $talk['speaker_bio'][$locale] ?? null;
                                @endphp
                                <div data-talk="{{ $talkIndex + 1 }}" @class(['flex flex-col gap-4', 'border-t border-ink/15 pt-6' => $talkIndex > 0])>
                                    @if (count($talks) > 1)
                                        <p class="text-sm font-semibold text-ink/70">{{ __('agenda.talk', ['number' => $talkIndex + 1]) }}</p>
                                    @endif
                                    <div class="flex items-center gap-4">
                                        <x-speaker-photo :photo="$talk['speaker_photo']" :name="$speaker" />
                                        <p class="font-semibold">{{ $speaker ?? __('agenda.speaker_tbd') }}</p>
                                    </div>
                                    @if ($bio)
                                        <p class="text-sm leading-relaxed text-ink/80">{{ $bio }}</p>
                                    @endif
                                    <h3 @class(['text-xl font-bold', 'text-ink/70' => ! $title])>{{ $title ?? __('agenda.title_tbd') }}</h3>
                                    <div>
                                        <p class="text-sm font-semibold">{{ __('agenda.outline_label') }}</p>
                                        <x-agenda-outline :text="$outline ?? __('agenda.outline_tbd')" @class(['mt-1 text-sm leading-relaxed', 'text-ink/80' => $outline, 'text-ink/70' => ! $outline]) />
                                    </div>
                                </div>
                            @endforeach
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </main>
    @include('landing.footer')
@endsection
