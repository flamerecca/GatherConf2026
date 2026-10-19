@props(['name', 'alt', 'width', 'height', 'sizes' => '(min-width: 1184px) 1120px, calc(100vw - 32px)', 'widths' => [480, 768, 1024]])
<picture>
    <source type="image/webp" sizes="{{ $sizes }}"
            srcset="{{ collect($widths)->map(fn (int $w) => Vite::asset('resources/images/'.$name.'-'.$w.'.webp').' '.$w.'w')->implode(', ') }}">
    <img src="{{ Vite::asset('resources/images/'.$name.'.png') }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}"
         loading="lazy" decoding="async" {{ $attributes }}>
</picture>
