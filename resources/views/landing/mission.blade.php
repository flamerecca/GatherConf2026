<section class="bg-deep text-on-deep">
    <div class="mx-auto max-w-6xl px-4 py-16">
        <x-responsive-image name="community-gathering-watercolor" avif loading="eager" fetchpriority="high" :alt="__('landing.gathering_alt')" :width="1024" :height="572"
                            class="aspect-video w-full rounded-2xl object-cover shadow-xl" />
        <div class="mt-12 max-w-3xl">
            <h2 class="text-3xl font-bold">{{ __('landing.mission_title') }}</h2>
            <p class="mt-6 text-lg leading-relaxed">{{ __('landing.mission_lead') }}</p>
            <p class="mt-4 leading-relaxed text-on-deep/80">{{ __('landing.mission_body') }}</p>
            @if (filled(__('landing.mission_quote')) && __('landing.mission_quote') !== 'landing.mission_quote')
                <p class="mt-8 border-l-4 border-gold pl-4 text-sm leading-relaxed text-on-deep/70 italic" lang="en">
                    {{ __('landing.mission_quote') }}
                </p>
            @endif
        </div>
    </div>
</section>
