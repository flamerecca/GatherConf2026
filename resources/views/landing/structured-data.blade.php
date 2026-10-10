@php
    $isEnglish = app()->getLocale() === 'en';
    $organizationId = route('landing').'#organization';
    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $organizationId,
                'name' => 'GatherConf',
                'url' => route('landing'),
                'logo' => asset('icon-512.png'),
                'email' => 'contact@mg.gatherconf.net',
            ],
            [
                '@type' => 'Event',
                'name' => __('landing.title'),
                'description' => __('landing.description'),
                'url' => $isEnglish ? route('landing.en') : route('landing'),
                'image' => [asset('og-image.jpg')],
                'startDate' => '2026-11-21T09:00:00+08:00',
                'endDate' => '2026-11-21T17:00:00+08:00',
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'inLanguage' => ['zh-Hant-TW', 'en'],
                'location' => [
                    '@type' => 'Place',
                    'name' => __('landing.place'),
                    'address' => [
                        '@type' => 'PostalAddress',
                        'streetAddress' => __('landing.address_street'),
                        'addressLocality' => __('landing.address_locality'),
                        'addressCountry' => 'TW',
                    ],
                ],
                'organizer' => ['@id' => $organizationId],
                'offers' => [
                    '@type' => 'Offer',
                    'url' => 'https://luma.com/wfjisb47',
                    'availability' => 'https://schema.org/InStock',
                ],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
