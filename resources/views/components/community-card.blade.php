@props(['community', 'showDescription' => false])
@php
    $name = $community['name'][app()->getLocale()];
@endphp
<a href="{{ $community['url'] }}" target="_blank" rel="noopener"
   class="group flex h-full flex-col items-center gap-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6 text-center transition hover:border-moss">
    <x-circle-logo :src="Vite::asset('resources/images/community-logo/'.$community['logo'])" :alt="$name" :fit="$community['logo_fit']" :padding="$community['logo_padding'] ?? 15" />
    <span class="text-lg font-semibold">{{ $name }}</span>
    @if ($showDescription)
        <span class="text-sm leading-relaxed text-ink/70">{{ $community['description'][app()->getLocale()] }}</span>
    @endif
    <span class="text-sm font-medium text-moss group-hover:underline">{{ __('communities.visit') }}</span>
</a>
