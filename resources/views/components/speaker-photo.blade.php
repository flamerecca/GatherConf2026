@props(['photo' => null, 'name' => null, 'size' => 'size-20'])
@if ($photo)
    <img src="{{ Vite::asset('resources/images/speakers/'.$photo) }}"
         alt="{{ __('agenda.speaker_photo_alt', ['name' => $name ?? '']) }}"
         loading="lazy" decoding="async"
         class="{{ $size }} shrink-0 rounded-2xl object-cover">
@else
    <div class="{{ $size }} flex shrink-0 items-center justify-center rounded-2xl border-2 border-dashed border-ink/60 text-ink/70"
         role="img" aria-label="{{ __('agenda.speaker_placeholder') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-1/2" aria-hidden="true">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21a8 8 0 0 1 16 0"/>
        </svg>
    </div>
@endif
