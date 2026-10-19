@props(['path', 'alt', 'fit' => 'contain', 'size' => 'size-40', 'padding' => 15])
@php
    $pixels = ['size-32' => 128, 'size-40' => 160, 'size-48' => 192][$size] ?? 160;
    $base = 'resources/images/'.preg_replace('/\.[^.]+$/', '', $path);
    $variants = collect(glob(base_path($base.'-*.webp')) ?: [])
        ->mapWithKeys(fn (string $file) => [(int) preg_replace('/^.*-(\d+)\.webp$/', '$1', $file) => 'resources/images/'.substr($file, strlen(resource_path('images')) + 1)])
        ->sortKeys();
@endphp
<picture>
    @if ($variants->isNotEmpty())
        <source type="image/webp" sizes="{{ $pixels }}px"
                srcset="{{ $variants->map(fn (string $file, int $width) => Vite::asset($file).' '.$width.'w')->implode(', ') }}">
    @endif
    <img src="{{ Vite::asset('resources/images/'.$path) }}" alt="{{ $alt }}" width="{{ $pixels }}" height="{{ $pixels }}"
         loading="lazy" decoding="async"
         class="{{ $size }} rounded-full ring-1 ring-ink/15 {{ $fit === 'cover' ? 'object-cover' : 'bg-white object-contain' }}"
         @if ($fit !== 'cover') style="padding: {{ (int) $padding }}%" @endif>
</picture>
