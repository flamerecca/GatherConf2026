<?php

/**
 * 議程清單，雙軌共八場。每軌四場，順序即為頁面上的顯示順序。
 * time 為該場的起訖時間，兩軌同一場次同時進行。
 * speaker、title、outline 先留空，確定後填入即可，頁面會自動改為顯示內容。
 * title 與 outline 需同時提供 zh_TW 與 en，留空時使用 null。
 * speaker_photo 為 resources/images/speakers 底下的檔名，留空時使用 null。
 *
 * @var list<array{id: string, sessions: list<array{id: string, time: string, speaker_name: ?string, speaker_photo: ?string, title: array{zh_TW: ?string, en: ?string}, outline: array{zh_TW: ?string, en: ?string}}>}>
 */
$session = fn (string $id, string $time): array => [
    'id' => $id,
    'time' => $time,
    'speaker_name' => null,
    'speaker_photo' => null,
    'title' => ['zh_TW' => null, 'en' => null],
    'outline' => ['zh_TW' => null, 'en' => null],
];

return [
    ['id' => 'a', 'sessions' => [
        $session('a1', '10:00 - 10:45'),
        $session('a2', '10:45 - 11:30'),
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
