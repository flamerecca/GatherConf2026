<?php

/**
 * 贊助商清單。tier 為 gold、silver、bronze 其中之一，logo 為 resources/images/sponsor-logo 底下的檔名。
 *
 * @var list<array{name: array{zh_TW: string, en: string}, logo: string, url: string, tier: string}>
 */
return [
    [
        'name' => [
            'zh_TW' => '兔給得資訊有限公司',
            'en' => 'Rabbit Gather Co., Ltd.',
        ],
        'logo' => 'rabbit-gather.png',
        'url' => 'https://www.rabbit-gather.com/',
        'tier' => 'gold',
    ],
];
