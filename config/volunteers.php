<?php

/**
 * 志工名單，依組別分群，組別與組內人員的順序即為頁面顯示順序。
 * name 為組別名稱，需同時提供 zh_TW 與 en。
 * members 為組內志工，name 為顯示在頁面上的名稱，建議使用志工本人同意公開的暱稱或姓名。
 * photo 為 resources/images/volunteers 底下的檔名，留空時使用同一資料夾的 default-bunny.svg。
 * 名單為空時，頁面顯示名單整理中的說明。
 *
 * @var list<array{name: array{zh_TW: string, en: string}, members: list<array{name: string, photo?: ?string}>}>
 */
return [
    [
        'name' => ['zh_TW' => '志工夥伴', 'en' => 'Volunteers'],
        'members' => [
            ['name' => '青', 'photo' => 'qing.jpg'],
            ['name' => '聖佑'],
            ['name' => 'ChAoS'],
            ['name' => '丁丁', 'photo' => 'dingding.jpg'],
            ['name' => 'Noelle'],
            ['name' => '櫛風'],
            ['name' => 'Teng'],
            ['name' => '均', 'photo' => 'jun.jpg'],
            ['name' => 'Yuyu', 'photo' => 'yuyu.jpg'],
            ['name' => 'Fearnot', 'photo' => 'fearnot.png'],
            ['name' => '松'],
            ['name' => 'Luke'],
            ['name' => '麵麵'],
        ],
    ],
];
