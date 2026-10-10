@props(['switchUrl', 'switchLabel', 'backLabel'])
<header class="mx-auto flex max-w-6xl items-center justify-between px-4 pt-6">
    <a href="{{ app()->getLocale() === 'en' ? route('landing.en') : route('landing') }}" class="flex items-center gap-4 font-semibold text-moss hover:underline">
        <img src="{{ Vite::asset('resources/images/GatherConf-Logo.png') }}" alt="{{ __('landing.logo_alt') }}" class="size-10 rounded-full">
        {{ $backLabel }}
    </a>
    <div class="flex items-center gap-4">
        <x-theme-toggle />
        <a href="{{ $switchUrl }}"
           class="rounded-full border border-ink/30 px-4 py-2 text-sm font-medium transition hover:bg-ink hover:text-parchment">
            {{ $switchLabel }}
        </a>
    </div>
</header>
