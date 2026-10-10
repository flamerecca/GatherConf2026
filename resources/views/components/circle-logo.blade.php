@props(['src', 'alt', 'fit' => 'contain', 'size' => 'size-40'])
@php
    $fits = ['cover' => 'object-cover', 'contain' => 'bg-white object-contain p-[15%]'];
@endphp
<img src="{{ $src }}" alt="{{ $alt }}" class="{{ $size }} rounded-full ring-1 ring-ink/15 {{ $fits[$fit] }}">
