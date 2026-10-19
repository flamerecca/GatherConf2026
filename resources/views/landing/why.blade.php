<section class="mx-auto max-w-6xl px-4 py-16">
    <h2 class="text-3xl font-bold">{{ __('landing.why_title') }}</h2>
    <p class="mt-6 max-w-3xl text-lg leading-relaxed text-ink/80">{{ __('landing.why_lead') }}</p>
    <figure class="mt-12">
        <x-responsive-image name="community-silos-watercolor" :alt="__('landing.why_image_alt')" :width="1024" :height="434"
                            class="w-full rounded-2xl shadow-xl" />
        <figcaption class="mt-4 grid grid-cols-2 gap-4 text-center font-semibold">
            <span>{{ __('landing.why_before') }}</span>
            <span class="text-moss">{{ __('landing.why_after') }}</span>
        </figcaption>
    </figure>
    <ul class="mt-12 grid gap-6 md:grid-cols-3">
        @foreach (__('landing.why_points') as [$heading, $description])
            <li class="rounded-2xl border border-ink/15 p-6">
                <h3 class="font-semibold text-moss">{{ $heading }}</h3>
                <p class="mt-2 leading-relaxed">{{ $description }}</p>
            </li>
        @endforeach
    </ul>
    <p class="mt-12 max-w-3xl border-l-4 border-gold pl-4 text-lg">{{ __('landing.why_closing') }}</p>
</section>
