@php
    $logoSizes = ['gold' => 'size-48', 'silver' => 'size-40', 'bronze' => 'size-32'];
    $sponsorsByTier = collect(config('sponsors'))->groupBy('tier');
@endphp
@if ($sponsorsByTier->isNotEmpty())
    <section class="mx-auto max-w-6xl px-4 py-16">
        <h2 class="text-3xl font-bold">{{ __('sponsors.heading') }}</h2>
        <p class="mt-6 max-w-3xl text-lg leading-relaxed text-ink/80">{{ __('sponsors.intro') }}</p>
        @foreach (['gold', 'silver', 'bronze'] as $tier)
            @if ($sponsorsByTier->has($tier))
                <div class="mt-12">
                    <h3 class="text-sm font-semibold tracking-widest text-copper uppercase">{{ __('sponsors.tiers.'.$tier) }}</h3>
                    <ul class="mt-6 flex flex-wrap gap-6">
                        @foreach ($sponsorsByTier[$tier] as $sponsor)
                            <li>
                                <a href="{{ $sponsor['url'] }}" target="_blank" rel="noopener"
                                   class="group flex h-full w-full flex-col items-center gap-4 rounded-2xl border border-ink/15 bg-parchment-dark p-6 text-center transition hover:border-moss sm:w-auto">
                                    <img src="{{ Vite::asset('resources/images/sponsor-logo/'.$sponsor['logo']) }}" alt="{{ $sponsor['name'] }}"
                                         class="{{ $logoSizes[$tier] }} rounded-2xl bg-white object-contain p-4">
                                    <span class="text-lg font-semibold">{{ $sponsor['name'] }}</span>
                                    <span class="text-sm font-medium text-moss group-hover:underline">{{ __('sponsors.visit') }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </section>
@endif
