<section class="mx-auto max-w-6xl px-4 py-16">
    <h2 class="text-3xl font-bold">{{ __('communities.heading') }}</h2>
    <p class="mt-6 max-w-3xl text-lg leading-relaxed text-ink/80">{{ __('communities.intro') }}</p>
    <ul class="mt-12 grid gap-6 sm:grid-cols-2 md:grid-cols-4">
        @foreach (config('communities') as $community)
            <li>
                <x-community-card :community="$community" />
            </li>
        @endforeach
    </ul>
    <a href="{{ app()->getLocale() === 'en' ? route('communities.en') : route('communities') }}"
       class="mt-12 inline-block font-semibold text-moss underline-offset-4 hover:underline">
        {{ __('communities.view_all') }} →
    </a>
</section>
