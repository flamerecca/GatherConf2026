<header class="mx-auto max-w-6xl px-4 pt-6">
    <nav class="flex items-center justify-end gap-4">
        <a href="{{ app()->getLocale() === 'en' ? route('conduct.en') : route('conduct') }}"
           class="text-sm font-medium underline-offset-4 hover:underline">
            {{ __('landing.conduct_link') }}
        </a>
        @php($isEn = app()->getLocale() === 'en')
        @auth('member')
            @php($carrots = auth('member')->user()->carrots)
            <a href="{{ $isEn ? route('member.center.en') : route('member.center') }}"
               @class([
                   'rounded-full px-4 py-2 text-sm transition',
                   'bg-moss font-medium text-on-accent hover:bg-ink' => $carrots >= 0,
                   'bg-red-700 font-bold text-white hover:bg-red-900 dark:bg-red-600 dark:hover:bg-red-800' => $carrots < 0,
               ])>
                {{ __('landing.member_center_carrots', ['count' => $carrots]) }}
            </a>
        @else
            <a href="{{ $isEn ? route('member.login.en') : route('member.login') }}"
               class="rounded-full bg-moss px-4 py-2 text-sm font-medium text-on-accent transition hover:bg-ink">
                {{ __('landing.member_login_link') }}
            </a>
        @endauth
        <x-theme-toggle />
        <a href="{{ app()->getLocale() === 'en' ? route('landing') : route('landing.en') }}"
           class="rounded-full border border-ink/30 px-4 py-2 text-sm font-medium transition hover:bg-ink hover:text-parchment">
            {{ __('landing.switch_label') }}
        </a>
    </nav>
</header>
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
    <img src="{{ Vite::asset('resources/images/GatherConf-Logo.png') }}" alt="{{ __('landing.logo_alt') }}"
         class="mx-auto w-full max-w-md rounded-full mix-blend-multiply dark:mix-blend-normal">
</section>
