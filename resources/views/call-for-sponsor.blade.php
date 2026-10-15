@extends('layouts.app')

@section('route', 'call-for-sponsor')
@section('title', __('cfs.title'))
@section('description', __('cfs.description'))

@section('content')
    @php
        $isEnglish = app()->getLocale() === 'en';
        $email = 'contact@mg.gatherconf.net';
        $mailto = 'mailto:'.$email.'?subject='.rawurlencode(__('cfs.mail_subject'));
        $tiers = __('landing.tiers');
        $tierBorders = ['gold' => 'border-tier-gold', 'silver' => 'border-tier-silver', 'bronze' => 'border-tier-bronze'];
        $tierDots = ['gold' => 'bg-tier-gold', 'silver' => 'bg-tier-silver', 'bronze' => 'bg-tier-bronze'];
        $benefits = collect($tiers)->flatMap(fn (array $tier) => $tier['benefits'])->unique()->values();
    @endphp
    <x-subpage-header :switch-url="$isEnglish ? route('call-for-sponsor') : route('call-for-sponsor.en')"
                      :switch-label="__('cfs.switch_label')" :back-label="__('cfs.back')" />

    <main id="main" tabindex="-1">
        <section class="mx-auto max-w-6xl px-4 py-12">
            <h1 class="text-4xl font-bold tracking-tight md:text-5xl">{{ __('cfs.heading') }}</h1>
            <p class="mt-6 max-w-3xl text-lg leading-relaxed text-ink/80">{{ __('cfs.lead') }}</p>
            <a href="{{ $mailto }}"
               class="mt-8 inline-block rounded-full bg-moss px-8 py-4 text-lg font-semibold text-on-accent shadow transition hover:bg-ink">
                {{ __('cfs.cta') }}
            </a>
        </section>

        <section class="mx-auto max-w-6xl px-4 py-12">
            <h2 class="text-3xl font-bold">{{ __('cfs.values_title') }}</h2>
            <ul class="mt-8 grid gap-6 sm:grid-cols-2">
                @foreach (__('cfs.values') as [$title, $body])
                    <li class="rounded-2xl border border-ink/15 bg-parchment-dark p-6">
                        <h3 class="text-xl font-bold text-moss">{{ $title }}</h3>
                        <p class="mt-3 leading-relaxed text-ink/80">{{ $body }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="mx-auto max-w-6xl px-4 py-12">
            <h2 class="text-3xl font-bold">{{ __('cfs.event_title') }}</h2>
            <dl class="mt-8 grid gap-6 sm:grid-cols-2">
                @foreach (__('cfs.event') as [$label, $value])
                    <div class="rounded-2xl border border-ink/15 bg-parchment-dark p-6">
                        <dt class="text-sm font-semibold tracking-widest text-copper uppercase">{{ $label }}</dt>
                        <dd class="mt-2 text-lg font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="bg-parchment-dark">
            <div class="mx-auto max-w-6xl px-4 py-16">
                <h2 class="text-3xl font-bold">{{ __('cfs.tiers_title') }}</h2>
                <p class="mt-4 text-ink/70">{{ __('cfs.tiers_intro') }}</p>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    @foreach ($tiers as $tier)
                        @php($tierKey = strtolower($tier['english']))
                        <article class="flex flex-col rounded-2xl border-4 {{ $tierBorders[$tierKey] }} bg-parchment p-8">
                            <p class="flex items-center gap-2 text-sm font-semibold tracking-widest text-copper uppercase">
                                <span class="size-3 rounded-full {{ $tierDots[$tierKey] }}" aria-hidden="true"></span>
                                {{ $tier['english'] }}
                            </p>
                            <h3 class="mt-2 text-2xl font-bold">{{ $tier['name'] }}</h3>
                            <p class="mt-4 text-4xl font-bold">{{ __('landing.price', ['amount' => $tier['amount']]) }}</p>
                            <ul class="mt-6 space-y-2">
                                @foreach ($tier['benefits'] as $benefit)
                                    <li class="flex gap-2">
                                        <span class="text-copper" aria-hidden="true">✦</span>
                                        <span>{{ $benefit }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 py-12">
            <h2 class="text-3xl font-bold">{{ __('cfs.compare_title') }}</h2>
            <div class="mt-8 overflow-x-auto rounded-2xl border border-ink/15">
                <table class="w-full min-w-xl border-collapse text-left">
                    <thead class="bg-parchment-dark">
                        <tr>
                            <th scope="col" class="p-4 text-sm font-semibold">{{ __('cfs.compare_benefit') }}</th>
                            @foreach ($tiers as $tier)
                                <th scope="col" class="p-4 text-center text-sm font-semibold">
                                    {{ $tier['name'] }}
                                    <span class="block text-xs font-normal text-ink/70">{{ __('landing.price', ['amount' => $tier['amount']]) }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($benefits as $benefit)
                            <tr class="border-t border-ink/15">
                                <th scope="row" class="p-4 font-medium">{{ $benefit }}</th>
                                @foreach ($tiers as $tier)
                                    <td class="p-4 text-center">
                                        @if (in_array($benefit, $tier['benefits'], true))
                                            <span class="text-moss" aria-hidden="true">✓</span>
                                            <span class="sr-only">{{ __('cfs.included') }}</span>
                                        @else
                                            <span class="text-ink/40" aria-hidden="true">—</span>
                                            <span class="sr-only">{{ __('cfs.not_included') }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-4 py-12">
            <h2 class="text-3xl font-bold">{{ __('cfs.apply_title') }}</h2>
            <p class="mt-4 max-w-3xl text-lg leading-relaxed text-ink/80">{{ __('cfs.apply_intro') }}</p>
            <ol class="mt-8 grid gap-6 md:grid-cols-3">
                @foreach (__('cfs.steps') as $index => [$title, $body])
                    <li class="rounded-2xl border border-ink/15 bg-parchment-dark p-6">
                        <p class="text-sm font-semibold tracking-widest text-copper">0{{ $index + 1 }}</p>
                        <h3 class="mt-2 text-xl font-bold">{{ $title }}</h3>
                        <p class="mt-3 leading-relaxed text-ink/80">{{ str_replace(':email', $email, $body) }}</p>
                    </li>
                @endforeach
            </ol>
            <a href="{{ $mailto }}"
               class="mt-8 inline-block rounded-full bg-moss px-8 py-4 text-lg font-semibold text-on-accent shadow transition hover:bg-ink">
                {{ __('cfs.apply_cta', ['email' => $email]) }}
            </a>
        </section>

        @include('landing.sponsors')
    </main>

    @include('landing.footer')
@endsection
