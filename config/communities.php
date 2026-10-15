<?php

/**
 * 合作社群清單。logo 為 resources/images/community-logo 底下的檔名。
 * logo_fit 為 cover 時，圖片鋪滿圓形，適合圓形構圖的 Logo。
 * 為 contain 時，圖片縮小置中，避免方形 Logo 的邊角被圓形裁掉。
 * description 為合作社群頁卡片上的簡短說明，需同時提供 zh_TW 與 en。
 * logo_padding 為 contain 時的內縮百分比，預設 15。數字越小，Logo 越大。
 *
 * slug 為固定識別，用於聯絡表單網址，公開後請勿更動。
 *
 * @var list<array{slug: string, name: array{zh_TW: string, en: string}, logo: string, logo_fit: string, logo_padding?: int, url: string, description: array{zh_TW: string, en: string}}>
 */
return [
    [
        'slug' => 'taiwan-kotlin-user-group',
        'name' => [
            'zh_TW' => 'Taiwan Kotlin User Group',
            'en' => 'Taiwan Kotlin User Group',
        ],
        'description' => [
            'zh_TW' => '推廣 Kotlin 程式語言的台灣社群，定期舉辦聚會，提供學習資源，讓開發者交流使用經驗。',
            'en' => 'A Taiwanese community promoting the Kotlin programming language, with regular meetups and learning resources for developers to share experience.',
        ],
        'logo' => 'TKUG.png',
        'logo_fit' => 'contain',
        'logo_padding' => 8,
        'url' => 'https://taiwan-kotlin-user-group.github.io/',
    ],
    [
        'slug' => 'digital-rogue',
        'name' => [
            'zh_TW' => '網路遊民探險中',
            'en' => 'Digital Rogue',
        ],
        'description' => [
            'zh_TW' => '推動在台灣數位遊牧的社群，協助符合條件的遠端工作者在台灣數位遊牧，提供相關資訊與支持。',
            'en' => 'A community promoting digital nomadism in Taiwan, helping eligible remote workers live and work as digital nomads in Taiwan, with information and support.',
        ],
        'logo' => 'digital-rogue.png',
        'logo_fit' => 'cover',
        'url' => 'https://digitalrogue.world/',
    ],
    [
        'slug' => 'taiwan-obsidian-user-group',
        'name' => [
            'zh_TW' => 'Taiwan Obsidian User Group',
            'en' => 'Taiwan Obsidian User Group',
        ],
        'description' => [
            'zh_TW' => '給 Obsidian 使用者的交流社群，定期舉辦聚會與工作坊，分享筆記術、外掛技巧與個人知識管理心得。',
            'en' => 'A community for Obsidian users, with regular meetups and workshops on note-taking, plugins and personal knowledge management.',
        ],
        'logo' => 'taiwan-obsidian-user-group.png',
        'logo_fit' => 'contain',
        'logo_padding' => 0,
        'url' => 'https://www.meetup.com/taiwan-obsidian-user-group/',
    ],
    [
        'slug' => 'gdg-taoyuan',
        'name' => [
            'zh_TW' => 'GDG 桃園',
            'en' => 'GDG Taoyuan',
        ],
        'description' => [
            'zh_TW' => '專注於 Google 相關技術的開發者社群，舉辦 lightning talk、工作坊與 hackathon。',
            'en' => 'A developer community focused on Google technologies, hosting lightning talks, workshops and hackathons.',
        ],
        'logo' => 'GDG-Taoyuan.png',
        'logo_fit' => 'contain',
        'logo_padding' => 0,
        'url' => 'https://gdg.community.dev/gdg-taoyuan/',
    ],
    [
        'slug' => 'buffet-dev',
        'name' => [
            'zh_TW' => '開發者 Buffet',
            'en' => 'Developer Buffet',
        ],
        'description' => [
            'zh_TW' => '由開發者自發組成的技術社群，聚會討論前後端、UI/UX、機器學習與軟體開發等多元議題。',
            'en' => 'A developer-run tech community whose gatherings cover frontend, backend, UI/UX, machine learning and software development.',
        ],
        'logo' => 'buffet-dev.png',
        'logo_fit' => 'cover',
        'url' => 'https://www.facebook.com/groups/buffet.dev',
    ],
    [
        'slug' => 'iplayground',
        'name' => [
            'zh_TW' => 'iPlayground',
            'en' => 'iPlayground',
        ],
        'description' => [
            'zh_TW' => '以 Apple 平台開發為主題的台灣開發者社群，每年舉辦 iPlayground 年會。',
            'en' => 'A Taiwanese developer community around Apple platform development, hosting the annual iPlayground conference.',
        ],
        'logo' => 'iPlayground.png',
        'logo_fit' => 'contain',
        'logo_padding' => 0,
        'url' => 'https://iplayground.io/',
    ],
    [
        'slug' => 'orbio-community',
        'name' => [
            'zh_TW' => '人本與科技共融發展協會',
            'en' => 'Orbio Community',
        ],
        'description' => [
            'zh_TW' => '協助專業者把職場經驗轉化為獨特價值的社群，目標是打造 AI 時代的共融生態。',
            'en' => 'A community helping professionals turn work experience into unique value, building an inclusive ecosystem for the AI era.',
        ],
        'logo' => 'Orbio.png',
        'logo_fit' => 'contain',
        'logo_padding' => 0,
        'url' => 'https://linktr.ee/orbio.community',
    ],
    [
        'slug' => 'wordpress-taiwan',
        'name' => [
            'zh_TW' => 'WordPress Taiwan',
            'en' => 'WordPress Taiwan',
        ],
        'description' => [
            'zh_TW' => '台灣的 WordPress 使用者與開發者社群，交流網站架設、佈景主題與外掛的使用經驗。',
            'en' => 'A community of WordPress users and developers in Taiwan, sharing experience on building sites, themes and plugins.',
        ],
        'logo' => 'wordpress-taiwan.png',
        'logo_fit' => 'contain',
        'logo_padding' => 0,
        'url' => 'https://www.facebook.com/groups/wordpresstw',
    ],
];
