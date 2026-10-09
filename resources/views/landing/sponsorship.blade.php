<section class="bg-parchment-dark">
    <div class="mx-auto max-w-6xl px-4 py-16">
        <div class="flex items-end justify-between gap-6">
            <div>
                <h2 class="text-3xl font-bold">{{ __('landing.sponsor_title') }}</h2>
                <p class="mt-3 text-ink/70">{{ __('landing.sponsor_intro') }}</p>
            </div>
            <img src="{{ Vite::asset('resources/images/bunny/NVHG.jpeg') }}" alt="{{ __('landing.mascot_alt') }}"
                 class="hidden h-40 mix-blend-multiply sm:block">
        </div>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach (__('landing.tiers') as $tier)
                <article @class([
                    'flex flex-col rounded-2xl p-8',
                    'bg-ink text-parchment shadow-xl' => $loop->first,
                    'bg-parchment border border-ink/15' => ! $loop->first,
                ])>
                    <p class="text-sm font-semibold tracking-widest uppercase {{ $loop->first ? 'text-gold' : 'text-copper' }}">{{ $tier['english'] }}</p>
                    <h3 class="mt-1 text-2xl font-bold">{{ $tier['name'] }}</h3>
                    <p class="mt-4 text-4xl font-bold">NT$ {{ $tier['amount'] }}</p>
                    <p class="mt-1 text-sm opacity-70">{{ $tier['slots'] }}</p>
                    <ul class="mt-6 space-y-2">
                        @foreach ($tier['benefits'] as $benefit)
                            <li class="flex gap-2">
                                <span class="{{ $loop->parent->first ? 'text-gold' : 'text-copper' }}" aria-hidden="true">✦</span>
                                <span>{{ $benefit }}</span>
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </div>
</section>
