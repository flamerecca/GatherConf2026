<header class="mx-auto max-w-6xl px-4 pt-6">
    <nav class="flex items-center justify-end gap-4">
        <a href="{{ app()->getLocale() === 'en' ? route('conduct.en') : route('conduct') }}"
           class="inline-flex min-h-10 items-center text-sm font-medium underline-offset-4 hover:underline">
            {{ __('landing.conduct_link') }}
        </a>
        <x-member-button />
        <x-theme-toggle />
        <a href="{{ app()->getLocale() === 'en' ? route('landing') : route('landing.en') }}"
           class="rounded-full border border-ink/30 px-4 py-2 text-sm font-medium transition hover:bg-ink hover:text-parchment">
            {{ __('landing.switch_label') }}
        </a>
    </nav>
</header>
