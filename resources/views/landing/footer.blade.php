<footer class="bg-deep py-12 text-center text-on-deep/80">
    <x-responsive-image name="GatherConf-Logo" :alt="__('landing.logo_alt')" :width="687" :height="687" :widths="[128, 256, 448, 687]"
                        sizes="96px" loading="lazy"
                        class="mx-auto mb-4 size-24 rounded-full" />
    <p class="font-semibold text-on-deep">{{ __('landing.name') }} GatherConf 2026</p>
    <p class="mt-2 text-sm">2026-11-21 · {{ __('landing.place') }}</p>
    <div class="mt-4 flex flex-wrap justify-center gap-x-6 gap-y-2">
        <a href="https://luma.com/wfjisb47" target="_blank" rel="noopener" class="inline-flex min-h-6 items-center text-gold underline">{{ __('landing.register_footer') }}</a>
        <a href="{{ app()->getLocale() === 'en' ? route('conduct.en') : route('conduct') }}" class="inline-flex min-h-6 items-center text-gold underline">{{ __('landing.conduct_link') }}</a>
        <a href="{{ app()->getLocale() === 'en' ? route('agenda.en') : route('agenda') }}" class="inline-flex min-h-6 items-center text-gold underline">{{ __('landing.agenda_link') }}</a>
        <a href="{{ app()->getLocale() === 'en' ? route('communities.en') : route('communities') }}" class="inline-flex min-h-6 items-center text-gold underline">{{ __('landing.communities_link') }}</a>
        <a href="{{ app()->getLocale() === 'en' ? route('call-for-sponsor.en') : route('call-for-sponsor') }}" class="inline-flex min-h-6 items-center text-gold underline">{{ __('landing.sponsor_link') }}</a>
        <a href="{{ app()->getLocale() === 'en' ? route('member.login.en') : route('member.login') }}" class="inline-flex min-h-6 items-center text-gold underline">{{ __('landing.member_login_link') }}</a>
    </div>
</footer>
