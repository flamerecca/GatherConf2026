<?php

/**
 * 贊助商清單。tier 為 gold、silver、bronze 其中之一，logo 為 resources/images/sponsor-logo 底下的檔名。
 * logo_fit 為 cover 時，圖片鋪滿圓形。為 contain 時，圖片縮小置中，避免方形 Logo 的邊角被圓形裁掉。
 *
 * @var list<array{name: array{zh_TW: string, en: string}, logo: string, logo_fit: string, url: string, tier: string}>
 */
return [
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
];
