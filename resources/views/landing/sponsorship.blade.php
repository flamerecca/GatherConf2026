@php
    $tierBorders = ['gold' => 'border-tier-gold', 'silver' => 'border-tier-silver', 'bronze' => 'border-tier-bronze'];
    $tierDots = ['gold' => 'bg-tier-gold', 'silver' => 'bg-tier-silver', 'bronze' => 'bg-tier-bronze'];
@endphp
<section class="bg-parchment-dark">
    <div class="mx-auto max-w-6xl px-4 py-16">
        <div class="flex items-end justify-between gap-6">
            <div>
                <h2 class="text-3xl font-bold">{{ __('landing.sponsor_title') }}</h2>
                <p class="mt-4 text-ink/70">{{ __('landing.sponsor_intro') }}</p>
            </div>
            <img src="{{ Vite::asset('resources/images/bunny/NVHG.jpeg') }}" alt="{{ __('landing.mascot_alt') }}"
                 class="hidden h-40 mix-blend-multiply sm:block dark:rounded-2xl dark:mix-blend-normal">
        </div>
        <div class="mt-12 grid gap-6 md:grid-cols-3">
            @foreach (__('landing.tiers') as $tier)
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
