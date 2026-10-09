<section class="mx-auto max-w-6xl px-4 py-16">
    <h2 class="text-3xl font-bold">{{ __('landing.overview_title') }}</h2>
    <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach (__('landing.stats') as [$number, $label])
            <div class="rounded-2xl bg-parchment-dark p-6 text-center">
                <p class="text-4xl font-bold text-copper">{{ $number }}</p>
                <p class="mt-1 text-sm font-medium">{{ $label }}</p>
            </div>
        @endforeach
    </div>
    <dl class="mt-10 grid gap-6 md:grid-cols-2">
        @foreach (__('landing.facts') as [$term, $description])
            <div class="rounded-2xl border border-ink/15 p-6">
                <dt class="font-semibold text-moss">{{ $term }}</dt>
                <dd class="mt-2 leading-relaxed">{{ $description }}</dd>
            </div>
        @endforeach
    </dl>
</section>
