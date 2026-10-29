@props(['text'])
@php
    $items = [];
    $intro = [];

    foreach (preg_split('/\R/u', (string) $text) as $line) {
        if (trim($line) === '') {
            continue;
        }

        if (preg_match('/^(\s*)-\s+(.*)$/u', $line, $match)) {
            if ($match[1] === '' || $items === []) {
                $items[] = ['text' => trim($match[2]), 'children' => []];
            } else {
                $items[array_key_last($items)]['children'][] = trim($match[2]);
            }

            continue;
        }

        $intro[] = trim($line);
    }
@endphp
@if ($items === [])
    <p {{ $attributes->class(['whitespace-pre-line']) }}>{{ $text }}</p>
@else
    <div {{ $attributes }}>
        @if ($intro !== [])
            <p class="whitespace-pre-line">{{ implode("\n", $intro) }}</p>
        @endif
        <ul data-outline-list class="list-disc space-y-2 pl-5">
            @foreach ($items as $item)
                <li>
                    <span class="font-semibold">{{ $item['text'] }}</span>
                    @if ($item['children'] !== [])
                        <ul class="mt-1 list-[circle] space-y-1 pl-5 text-ink/80">
                            @foreach ($item['children'] as $child)
                                <li>{{ $child }}</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
