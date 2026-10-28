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
        array_replace($session('a1', '10:00 - 10:45'), [
            'speaker_name' => 'Zona Wang',
            'speaker_photo' => 'zona.jpg',
            'speaker_bio' => [
                'zh_TW' => 'Hi，我是 Zona，來自 LINE Taiwan DevRel 團隊。負責規劃與推廣開發者關係相關活動，包含技術社群合作、品牌推廣與內容策展，持續推動 LINE 與開發者社群的連結與交流。',
                'en' => 'Hi, I am Zona from the LINE Taiwan DevRel team. I plan and promote developer relations activities, including technical community partnerships, brand promotion and content curation, and keep strengthening the connection and exchange between LINE and developer communities.',
            ],
            'title' => [
                'zh_TW' => 'Agent 一定要用框架嗎？從 LINE Bot 實戰看 Google ADK 的價值',
                'en' => 'Do Agents Really Need a Framework? The Value of Google ADK Through a Hands-on LINE Bot',
            ],
            'outline' => [
                'zh_TW' => '不用框架也能開發 AI Agent，為什麼還需要 Google ADK？本次分享將從一個已完成的 LINE Bot 出發，實際改造成 Google ADK 架構，比較兩種開發方式在工具調用、流程控制、測試與維護上的差異。透過改造過程中的架構選擇與實作經驗，帶你了解 Google ADK 能解決哪些問題、帶來哪些成本，以及什麼時候值得導入 Agent Framework。',
                'en' => 'You can build an AI agent without a framework, so why do you still need Google ADK? This talk starts from a finished LINE Bot and rebuilds it on Google ADK, comparing the two approaches in tool calling, flow control, testing and maintenance. Through the architecture choices and hands-on experience along the way, it explains what problems Google ADK solves, what costs it brings, and when it is worth adopting an agent framework.',
            ],
        ]),
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
        array_replace($session('b2', '10:45 - 11:30'), [
            'speaker_name' => 'Maple Kuo',
            'speaker_photo' => 'Maple.jpeg',
            'speaker_bio' => [
                'zh_TW' => '曾任《2026 Hello World Dev Conference》講者，從 GPT-3.5 時代開始 vibe coding，一去不返從英文系轉職到軟體業，在 AI 時代出身的軟體工程師。',
                'en' => 'A former speaker at the 2026 Hello World Dev Conference. Started vibe coding in the GPT-3.5 era and never looked back, switching from an English major to the software industry. A software engineer who came up in the age of AI.',
            ],
            'title' => [
                'zh_TW' => '那些跟 Claude Code 有關的兩三事',
                'en' => 'A Few Things About Claude Code',
            ],
            'outline' => [
                'zh_TW' => '2026 年，軟體工程師早就被 AI「取代」無數次了。身為一個 AI 時代出身的軟體工程師，從踏入這個產業的第一天起，AI Coding 就是我的日常。照理來說，我應該比誰都更相信 AI 終將取代工程師這件事，但實際踏入火場，日子其實沒那麼悲壯，我反而認為有經驗的工程師，會用 AI 的話是最如魚得水的那群人。

既然 AI Coding 是趨勢和未來，那如何與 Agent 協作，就是留下來的人最重要的課題。這場分享會聊聊 Claude Code 的一些機制，以及我在 Claude Code 每天使用的工作流和如何設計。',
                'en' => 'In 2026, software engineers have already been "replaced" by AI countless times. As an engineer who came up in the age of AI, AI coding has been part of my daily life since my first day in the industry. By rights, I should believe more than anyone that AI will eventually replace engineers, but once in the thick of it, life is not nearly that grim. I actually think experienced engineers who know how to use AI are the ones who thrive most.

Since AI coding is the trend and the future, how to collaborate with agents is the most important question for those who stay. This talk covers some of the mechanisms behind Claude Code, plus the workflow I use in Claude Code every day and how I design it.',
            ],
        ]),
        array_replace($session('b3', '13:00 - 13:45'), [
            'speaker_name' => 'Sasha Denisov',
            'speaker_photo' => 'Sasha.jpg',
            'speaker_bio' => [
                'zh_TW' => 'Sasha Denisov is a software engineer and solution architect with over 20 years of experience building scalable, production-ready systems across mobile, web, backend, cloud, and AI. As CTO of Brainform.ai, he leads the development of an agentic commerce platform with AI agents for sales, customer support, and knowledge management. His current focus is Edge AI. Through Yoh, he supports Google’s AI Edge team. He is also the creator and maintainer of Flutter Edge AI (formerly flutter_gemma), an open-source framework for local AI inference across six platforms. Sasha is a Google Developer Expert for Cloud AI, Firebase, Flutter, and Dart, co-organizes the Flutter Berlin community, and has spoken at 30+ conferences worldwide, including WeAreDevelopers, LDX3, Fluttercon, and Droidcon.',
                'en' => 'Sasha Denisov is a software engineer and solution architect with over 20 years of experience building scalable, production-ready systems across mobile, web, backend, cloud, and AI. As CTO of Brainform.ai, he leads the development of an agentic commerce platform with AI agents for sales, customer support, and knowledge management. His current focus is Edge AI. Through Yoh, he supports Google’s AI Edge team. He is also the creator and maintainer of Flutter Edge AI (formerly flutter_gemma), an open-source framework for local AI inference across six platforms. Sasha is a Google Developer Expert for Cloud AI, Firebase, Flutter, and Dart, co-organizes the Flutter Berlin community, and has spoken at 30+ conferences worldwide, including WeAreDevelopers, LDX3, Fluttercon, and Droidcon.',
            ],
            'title' => [
                'zh_TW' => 'Edge AI: How Far Have We Come?',
                'en' => 'Edge AI: How Far Have We Come?',
            ],
            'outline' => [
                'zh_TW' => 'Running intelligence on device once felt like a distant promise, constrained by compute, power, and tooling. Today, lightweight models and specialized hardware have reshaped the landscape. This talk cuts through the hype to examine how far Edge AI has truly come, evaluating real-world capabilities, persistent trade-offs, and where the boundaries of edge deployment lie today.',
                'en' => 'Running intelligence on device once felt like a distant promise, constrained by compute, power, and tooling. Today, lightweight models and specialized hardware have reshaped the landscape. This talk cuts through the hype to examine how far Edge AI has truly come, evaluating real-world capabilities, persistent trade-offs, and where the boundaries of edge deployment lie today.',
            ],
        ]),
        $session('b4', '13:45 - 14:30'),
    ]],
];
