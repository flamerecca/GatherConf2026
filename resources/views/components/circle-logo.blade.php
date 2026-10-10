@props(['src', 'alt', 'fit' => 'contain', 'size' => 'size-40', 'padding' => 15])
<img src="{{ $src }}" alt="{{ $alt }}" class="{{ $size }} rounded-full ring-1 ring-ink/15 {{ $fit === 'cover' ? 'object-cover' : 'bg-white object-contain' }}"
     @if ($fit !== 'cover') style="padding: {{ (int) $padding }}%" @endif>
