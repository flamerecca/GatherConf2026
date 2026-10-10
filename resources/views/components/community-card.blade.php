@props(['community'])
<a href="{{ $community['url'] }}" target="_blank" rel="noopener"
   class="group flex h-full flex-col items-center gap-4 rounded-2xl border border-ink/15 bg-parchment-dark p-6 text-center transition hover:border-moss">
    <img src="{{ Vite::asset('resources/images/community-logo/'.$community['logo']) }}" alt="{{ $community['name'] }}"
         class="size-40 rounded-2xl bg-white object-contain p-4">
    <span class="text-lg font-semibold">{{ $community['name'] }}</span>
    <span class="text-sm font-medium text-moss group-hover:underline">{{ __('communities.visit') }}</span>
</a>
