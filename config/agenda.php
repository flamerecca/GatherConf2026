<?php

/**
 * 議程清單，雙軌共八場。每軌四場，順序即為頁面上的顯示順序。
 * date 為活動日期，time 為該場的起訖時間，兩軌同一場次同時進行。
 * speaker、title、outline 先留空，確定後填入即可，頁面會自動改為顯示內容。
 * title 與 outline 需同時提供 zh_TW 與 en，留空時使用 null。
 * speaker_photo 為 resources/images/speakers 底下的檔名，留空時使用 null。
 * speaker_bio 為講者介紹，需同時提供 zh_TW 與 en，留空時使用 null。
 *
 * @var list<array{id: string, sessions: list<array{id: string, date: string, time: string, speaker_name: ?string, speaker_photo: ?string, speaker_bio: array{zh_TW: ?string, en: ?string}, title: array{zh_TW: ?string, en: ?string}, outline: array{zh_TW: ?string, en: ?string}}>}>
 */
$session = fn (string $id, string $time): array => [
    'id' => $id,
    'date' => '2026-11-21',
    'time' => $time,
    'speaker_name' => null,
    'speaker_photo' => null,
    'speaker_bio' => ['zh_TW' => null, 'en' => null],
    'title' => ['zh_TW' => null, 'en' => null],
    'outline' => ['zh_TW' => null, 'en' => null],
];

return [
    ['id' => 'a', 'sessions' => [
        $session('a1', '10:00 - 10:45'),
        array_replace($session('a2', '10:45 - 11:30'), [
            'speaker_name' => 'Matthias Geisler',
            'speaker_photo' => 'Matthias.jpg',
            'speaker_bio' => [
                'zh_TW' => 'Hey I am Matthias and I work for some time now already in Tech. I use Kotlin Multiplatform before it was Multiplatform and build mostly things for Android. I co-organize some staff in Berlin like the KUG Berlin, Berlindroid, XTC Berlin and more. And of course I study as most currently the pearls of AI.',
                'en' => 'Hey I am Matthias and I work for some time now already in Tech. I use Kotlin Multiplatform before it was Multiplatform and build mostly things for Android. I co-organize some staff in Berlin like the KUG Berlin, Berlindroid, XTC Berlin and more. And of course I study as most currently the pearls of AI.',
            ],
            'title' => [
                'zh_TW' => 'Beyond the Code: Building Community in the Age of AI',
                'en' => 'Beyond the Code: Building Community in the Age of AI',
            ],
            'outline' => [
                'zh_TW' => 'As AI accelerates code generation, the technical barrier drops—yet the human element matters more than ever. Code can be synthesized, but trust, mentorship, and collective wisdom cannot. This talk explores how developer communities must evolve alongside AI, ensuring we leverage automated intelligence while cultivating the human connection that truly drives innovation forward.',
                'en' => 'As AI accelerates code generation, the technical barrier drops—yet the human element matters more than ever. Code can be synthesized, but trust, mentorship, and collective wisdom cannot. This talk explores how developer communities must evolve alongside AI, ensuring we leverage automated intelligence while cultivating the human connection that truly drives innovation forward.',
            ],
        ]),
        $session('a3', '13:00 - 13:45'),
        $session('a4', '13:45 - 14:30'),
    ]],
    ['id' => 'b', 'sessions' => [
        $session('b1', '10:00 - 10:45'),
        $session('b2', '10:45 - 11:30'),
        $session('b3', '13:00 - 13:45'),
        $session('b4', '13:45 - 14:30'),
    ]],
];
