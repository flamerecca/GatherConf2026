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

/**
 * 一個場次有兩位講者、兩個講題時，在場次內加入 talks，內容為兩筆講題，欄位與單講者相同，順序即為頁面顯示順序。
 * 有 talks 時，頁面只讀取 talks 內的講者與講題欄位，場次本身的 speaker_name、title 等欄位不再使用。
 */
$talk = fn (): array => [
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
        array_replace($session('a3', '13:00 - 13:45'), [
            'speaker_name' => 'Eva Chu',
            'speaker_photo' => 'EvAChU.jpg',
            'speaker_bio' => [
                'zh_TW' => '擁有超過 16 年的 UX 策略與產品設計經驗，現為 Women Techmakers (WTM) Taipei Ambassador 與人本與科技共融發展協會理事長。自 2020 年起累計主辦 112 場跨領域技術活動、串聯 1,100+ 位講者，並投入 26 場公開演講與 240 小時的職涯導師輔導，影響超過 6,800+ 名科技人才。擅長將十餘年的 UX 設計洞察與商業策略結合，轉化為與業界夥伴交流的實戰養分，透過社群將真實問題帶入討論、與大家一同探索並解決問題。',
                'en' => 'Eva has more than 16 years of experience in UX strategy and product design, and is currently the Women Techmakers (WTM) Taipei Ambassador and the Chairperson of the Human-Centered Technology Inclusion Development Association. Since 2020 she has organized 112 cross-disciplinary tech events and connected 1,100+ speakers, and has given 26 public talks and 240 hours of career mentoring, reaching more than 6,800 tech professionals. She combines over a decade of UX design insight with business strategy into practical lessons shared with industry peers, bringing real problems into community discussions to explore and solve them together.',
            ],
            'title' => [
                'zh_TW' => '從 Vibe Coding 踩坑到培養共創默契：我用 Antigravity 打造產品的 UX、Agentic UX 與 AX 實戰',
                'en' => 'From Vibe Coding Pitfalls to Building Co-Creation Rapport: Hands-on UX, Agentic UX and AX in Building a Product with Antigravity',
            ],
            'outline' => [
                'zh_TW' => '純靠「憑感覺許願」的 Vibe Coding，往往在第 4 天就會變成改不動的違章建築。 在這場 45 分鐘的分享中，我想聊聊自己用 Google Antigravity 開發 Side Project 時踩過的真實大坑，以及我如何結合矽谷最新的 AX（Agent Experience，代理體驗）、Context Engineering（脈絡工程） 與 Agentic UX（人機協作體驗），一步步建立起 AGENTS.md 協作指南與自動化防呆護欄（Guardrails），讓我和 AI 從一開始的「雞同鴨講、各自猜謎」，慢慢對齊頻率、培養出默契，成為一起守護產品 UX 的共創夥伴。',
                'en' => 'Vibe coding on nothing but "wishing by feel" often turns into an unfixable shanty by day four. In this 45-minute talk, I share the real pitfalls I hit building a side project with Google Antigravity, and how I combined the latest Silicon Valley thinking on AX (Agent Experience), Context Engineering and Agentic UX (human-agent collaboration experience) to build an AGENTS.md collaboration guide and automated guardrails step by step, so that the AI and I went from talking past each other and guessing blindly to aligning, building rapport, and becoming co-creators who protect the product UX together.',
            ],
        ]),
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
        array_replace($session('b4', '13:45 - 14:30'), [
            'talks' => [
                array_replace($talk(), [
                    'speaker_name' => '邱伊嫻 Vivian',
                    'speaker_photo' => 'vivian.jpg',
                    'speaker_bio' => [
                        'zh_TW' => '菩語國際設計負責人| 界構國際空間設計師協會 理事長
室內設計從業20年、服務超過300個家庭，中原大學室內設計碩士。自認3C白癡、不會寫程式，卻用 AI把整套設計流程做成工具，一個人做出比過去八人團隊更高的營業額。',
                        'en' => 'Head of Puyu International Design | Chairperson of the International Space Designers Association.
A 20-year interior designer who has served more than 300 families, with a master\'s degree in interior design from Chung Yuan Christian University. Describing herself as hopeless with gadgets and unable to code, she used AI to turn her whole design workflow into tools, and now earns higher revenue on her own than her former eight-person team did.',
                    ],
                    'title' => [
                        'zh_TW' => '不會寫程式的室內設計師，怎麼做出自己的工作系統',
                        'en' => 'An Interior Designer Who Cannot Code: How to Build Your Own Work System',
                    ],
                    'outline' => [
                        'zh_TW' => '- 01 為什麼設計師要自己做工具
  - 現成軟體不懂系統櫃報價，也不懂工地流程
- 02 一個案子的前中後
  - 居家性格心理測驗、需求調查表、3D系統櫃平台、專案管理 ERP
- 03 讓客戶找到你
  - 官網 SEO/GEO/AEO、LINE官方帳號 LIFF、臉書自動貼文搭配GPT 生圖、社群數據分析
- 04 把專業講成 AI 聽得懂的需求
  - 一次只講一件事、給圖、給實際案例
- 05 AI 的極限
  - 資料正確性、報價邏輯、驗收方法，哪些一定要自己把關',
                        'en' => '- 01 Why designers should build their own tools
  - Off-the-shelf software understands neither built-in cabinet quotes nor construction site workflows
- 02 Before, during and after a project
  - A home personality quiz, a needs survey, a 3D cabinet platform and a project management ERP
- 03 Helping clients find you
  - Website SEO/GEO/AEO, a LINE official account with LIFF, automatic Facebook posts with GPT-generated images, and social media analytics
- 04 Explaining your expertise so AI understands
  - Talk about one thing at a time, give images, give real cases
- 05 The limits of AI
  - Data accuracy, quotation logic and acceptance methods: what you must always check yourself',
                    ],
                ]),
                array_replace($talk(), [
                    'speaker_name' => '楊政翰/藏鏡羊',
                    'speaker_photo' => 'capybara_18do.jpg',
                    'speaker_bio' => [
                        'zh_TW' => '正從商業攝影轉型投入 AI 視覺產業，已用 AI 承接多個政府機關與工廠的行銷案。',
                        'en' => 'Transitioning from commercial photography into the AI visual industry, and has already taken on marketing projects for several government agencies and factories with AI.',
                    ],
                    'title' => [
                        'zh_TW' => '一位攝影師的AI 轉型實踐',
                        'en' => 'A Photographer\'s Journey of AI Transformation',
                    ],
                    'outline' => [
                        'zh_TW' => '- 01 轉型的起點
  - 商業攝影、大圖印刷、網拍組圖需求大減，手機修圖與短影音取代傳統媒體
- 02 公部門| 體育局
  - 先用AI 分鏡把拍攝想法變成畫面，再做運動短片與人物字卡
- 03 在地|農會
  - 同一個主題，延伸成美食主視覺與情境短片
- 04 安全議題| 消防安檢
  - 人物講解搭配情境畫面，把抽象議題講到看得懂
- 05 企業|日商與鋁門窗工廠
  - 讓設備出現在服務情境裡，實拍照片變成社群行銷內容
- 06 數字人出鏡
  - 從口播稿到交付，生成成功不等於可以直接用
- 07 下一步
  - 從攝影轉型為行銷整合，協助傳統產業轉型',
                        'en' => '- 01 Where the transition began
  - Demand for commercial photography, large-format printing and online store image sets has dropped sharply, as phone retouching and short videos replace traditional media
- 02 Public sector | Sports Administration
  - Use AI storyboards to turn shooting ideas into visuals first, then make sports short films and athlete name cards
- 03 Local | Farmers\' Association
  - One theme extended into a food key visual and a scenario short film
- 04 Safety | Fire safety inspection
  - A presenter explanation combined with scenario visuals, making an abstract issue easy to understand
- 05 Business | Japanese company and aluminum door and window factory
  - Place the equipment in service scenarios, and turn real photos into social media marketing content
- 06 Digital human on camera
  - From voiceover script to delivery, a successful generation does not mean it is ready to use
- 07 What is next
  - Moving from photography to integrated marketing, helping traditional industries transform',
                    ],
                ]),
            ],
        ]),
    ]],
];
