@php
    $locale = app()->getLocale();
@endphp
<section class="mx-auto max-w-6xl px-4 py-16" aria-labelledby="landing-agenda-heading">
    <h2 id="landing-agenda-heading" class="text-3xl font-bold">{{ __('agenda.heading') }}</h2>
    <p class="mt-6 max-w-3xl text-lg leading-relaxed text-ink/80">{{ __('agenda.intro') }}</p>
    <div class="mt-12 grid gap-6 md:grid-cols-2">
        @foreach (config('agenda') as $track)
            <div class="rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
                <h3 class="text-xl font-bold text-moss">{{ __('agenda.track', ['name' => strtoupper($track['id'])]) }}</h3>
                <ol class="mt-4 space-y-4">
                    @foreach ($track['sessions'] as $index => $session)
                        @php
                            $title = $session['title'][$locale] ?? null;
                        @endphp
                        <li data-landing-session="{{ $session['id'] }}">
                            <p class="text-sm font-semibold tracking-widest text-copper uppercase">
                                {{ __('agenda.session', ['number' => $index + 1]) }}
                                <span aria-hidden="true">·</span>
                                <time>{{ $session['time'] }}</time>
                            </p>
                            <p @class(['mt-1 font-semibold', 'text-ink/70' => ! $title])>{{ $title ?? __('agenda.title_tbd') }}</p>
                            <p class="text-sm text-ink/80">{{ $session['speaker_name'] ?? __('agenda.speaker_tbd') }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
    <a href="{{ $locale === 'en' ? route('agenda.en') : route('agenda') }}"
       class="mt-12 inline-block font-semibold text-moss underline-offset-4 hover:underline">
        {{ __('agenda.view_all') }} →
    </a>
</section>
