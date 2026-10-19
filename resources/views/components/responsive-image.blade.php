@props(['name', 'alt', 'width', 'height', 'sizes' => '(min-width: 1184px) 1120px, calc(100vw - 32px)', 'widths' => [360, 480, 576, 672, 768, 864, 960, 1024], 'ext' => 'png', 'loading' => 'lazy', 'avif' => false])
<picture>
    @if ($avif)
        <source type="image/avif" sizes="{{ $sizes }}"
                srcset="{{ collect($widths)->map(fn (int $w) => Vite::asset('resources/images/'.$name.'-'.$w.'.avif').' '.$w.'w')->implode(', ') }}">
    @endif
    <source type="image/webp" sizes="{{ $sizes }}"
            srcset="{{ collect($widths)->map(fn (int $w) => Vite::asset('resources/images/'.$name.'-'.$w.'.webp').' '.$w.'w')->implode(', ') }}">
    <img src="{{ Vite::asset('resources/images/'.$name.'.'.$ext) }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}"
         loading="{{ $loading }}" decoding="async" {{ $attributes }}>
</picture>
