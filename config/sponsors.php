<?php

/**
 * 贊助商清單。tier 為 gold、silver、bronze 其中之一，logo 為 resources/images/sponsor-logo 底下的檔名。
 * logo_fit 為 cover 時，圖片鋪滿圓形。為 contain 時，圖片縮小置中，避免方形 Logo 的邊角被圓形裁掉。
 * logo_padding 為 contain 時的內縮百分比，預設 15。數字越小，Logo 越大。
 *
 * @var list<array{name: array{zh_TW: string, en: string}, logo: string, logo_fit: string, logo_padding?: int, url: string, tier: string}>
 */
return [
    [
        'name' => [
            'zh_TW' => '領角鴞資訊整合有限公司',
            'en' => 'Leadowl Information Integration Co., Ltd.',
        ],
        'logo' => 'LeadOwl.png',
        'logo_fit' => 'contain',
        'logo_padding' => 8,
        'url' => 'https://odoo.leadowl.tw/',
        'tier' => 'gold',
    ],
    [
        'name' => [
            'zh_TW' => '兔給得資訊有限公司',
            'en' => 'Rabbit Gather Co., Ltd.',
        ],
        'logo' => 'rabbit-gather.png',
        'logo_fit' => 'contain',
        'url' => 'https://www.rabbit-gather.com/',
        'tier' => 'gold',
    ],
    [
        'name' => [
            'zh_TW' => 'JetBrains',
            'en' => 'JetBrains',
        ],
        'logo' => 'JetBrains.png',
        'logo_fit' => 'contain',
        'logo_padding' => 5,
        'url' => 'https://www.jetbrains.com/',
        'tier' => 'bronze',
    ],
];
