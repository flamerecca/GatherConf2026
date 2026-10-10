<footer class="bg-deep py-12 text-center text-on-deep/80">
    <img src="{{ Vite::asset('resources/images/GatherConf-Logo.png') }}" alt="{{ __('landing.logo_alt') }}"
         class="mx-auto mb-4 size-24 rounded-full">
    <p class="font-semibold text-on-deep">{{ __('landing.name') }} GatherConf 2026</p>
    <p class="mt-2 text-sm">2026-11-21 · {{ __('landing.place') }}</p>
    <div class="mt-4 flex flex-wrap justify-center gap-x-6 gap-y-2">
        <a href="https://luma.com/wfjisb47" target="_blank" rel="noopener" class="text-gold underline">{{ __('landing.register_footer') }}</a>
        <a href="{{ app()->getLocale() === 'en' ? route('conduct.en') : route('conduct') }}" class="text-gold underline">{{ __('landing.conduct_link') }}</a>
        <a href="{{ app()->getLocale() === 'en' ? route('communities.en') : route('communities') }}" class="text-gold underline">{{ __('landing.communities_link') }}</a>
    </div>
</footer>
