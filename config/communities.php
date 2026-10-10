<?php

/**
 * 合作社群清單。logo 為 resources/images/community-logo 底下的檔名。
 * logo_fit 為 cover 時，圖片鋪滿圓形，適合圓形構圖的 Logo。
 * 為 contain 時，圖片縮小置中，避免方形 Logo 的邊角被圓形裁掉。
 * logo_padding 為 contain 時的內縮百分比，預設 15。數字越小，Logo 越大。
 *
 * @var list<array{name: array{zh_TW: string, en: string}, logo: string, logo_fit: string, logo_padding?: int, url: string}>
 */
return [
    [
        'name' => [
            'zh_TW' => 'Taiwan Kotlin User Group',
            'en' => 'Taiwan Kotlin User Group',
        ],
        'logo' => 'TKUG.png',
        'logo_fit' => 'contain',
        'logo_padding' => 8,
        'url' => 'https://taiwan-kotlin-user-group.github.io/',
    ],
    [
        'name' => [
            'zh_TW' => '網路遊民探險中',
            'en' => 'Digital Rogue',
        ],
        'logo' => 'digital-rogue.png',
        'logo_fit' => 'cover',
        'url' => 'https://digitalrogue.world/',
    ],
    [
        'name' => [
            'zh_TW' => 'Taiwan Obsidian User Group',
            'en' => 'Taiwan Obsidian User Group',
        ],
        'logo' => 'taiwan-obsidian-user-group.png',
        'logo_fit' => 'contain',
        'logo_padding' => 0,
        'url' => 'https://www.meetup.com/taiwan-obsidian-user-group/',
    ],
    [
        'name' => [
            'zh_TW' => 'GDG 桃園',
            'en' => 'GDG Taoyuan',
        ],
        'logo' => 'GDG-Taoyuan.png',
        'logo_fit' => 'contain',
        'logo_padding' => 0,
        'url' => 'https://gdg.community.dev/gdg-taoyuan/',
    ],
];
