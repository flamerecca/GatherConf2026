<section class="mx-auto grid max-w-6xl items-center gap-8 px-4 py-12 md:grid-cols-2 md:py-16">
    <div>
        <p class="text-sm font-semibold tracking-widest text-copper uppercase">GatherConf 2026</p>
        <h1 class="mt-4 text-5xl font-bold tracking-tight md:text-6xl">{{ __('landing.name') }}</h1>
        <p class="mt-6 text-xl leading-relaxed text-ink/80">{{ __('landing.hero_tagline') }}</p>
        <dl class="mt-8 space-y-2 text-lg">
            <div class="flex gap-4">
                <dt class="font-semibold text-moss">{{ __('landing.time_label') }}</dt>
                <dd>2026-11-21 09:00-17:00</dd>
            </div>
            <div class="flex gap-4">
                <dt class="font-semibold text-moss">{{ __('landing.place_label') }}</dt>
                <dd>{{ __('landing.place') }}</dd>
            </div>
        </dl>
        <a href="https://luma.com/wfjisb47" target="_blank" rel="noopener"
           class="mt-12 inline-block rounded-full bg-moss px-8 py-4 text-lg font-semibold text-on-accent shadow transition hover:bg-ink">
            {{ __('landing.register') }}
        </a>
    </div>
    <x-responsive-image name="GatherConf-Logo" :alt="__('landing.logo_alt')" :width="687" :height="687" :widths="[128, 256, 448, 687]"
                        sizes="(min-width: 480px) 448px, calc(100vw - 32px)" loading="eager"
                        class="mx-auto w-full max-w-md rounded-full mix-blend-multiply dark:mix-blend-normal" />
</section>
