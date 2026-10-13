<?php

return [
    'login' => [
        'title' => '會員登入 | 共聚年會 GatherConf 2026',
        'description' => '登入共聚年會會員。',
        'heading' => '會員登入',
        'password_heading' => '使用密碼登入',
        'submit' => '登入',
        'remember' => '記住我',
        'forgot' => '忘記密碼？',
        'register_prompt' => '還沒有帳號？',
        'register_link' => '註冊會員',
        'link_heading' => '或是寄送登入連結',
        'link_intro' => '輸入 Email，我們會寄一封一次性登入連結給你，不需要輸入密碼。',
        'link_submit' => '寄送登入連結',
        'link_sent' => '如果這個 Email 已註冊，登入連結已經寄出，請到信箱查看。',
    ],
    'center' => [
        'title' => '會員中心 | 共聚年會 GatherConf 2026',
        'heading' => '會員中心',
        'welcome' => '嗨，:name',
        'organization' => '所屬社群或公司',
        'not_set' => '未填寫',
        'logout' => '登出',
    ],
    'register' => [
        'title' => '註冊會員 | 共聚年會 GatherConf 2026',
        'description' => '註冊成為共聚年會會員。',
        'heading' => '註冊會員',
        'submit' => '註冊',
        'have_account' => '已經有帳號了？',
        'login_link' => '前往登入',
    ],
    'back' => '回到首頁',
    'switch_label' => 'English',
    'fields' => [
        'name' => '姓名',
        'email' => 'Email',
        'password' => '密碼',
        'password_confirmation' => '確認密碼',
        'organization' => '所屬社群或公司，選填',
    ],
    'errors' => [
        'failed' => 'Email 或密碼不正確。',
        'inactive' => '此帳號已被停用，如有疑問請聯絡我們。',
        'link_invalid' => '這個登入連結已失效，請重新要求一個。',
    ],
    'mail' => [
        'login_link' => [
            'subject' => '你的 GatherConf 登入連結',
            'intro' => '請點擊下方按鈕登入，連結 15 分鐘內有效，只能使用一次。',
            'action' => '登入 GatherConf',
            'outro' => '如果不是你要求的，請忽略這封信，不會有任何變更。',
        ],
        'verify' => [
            'subject' => '請驗證你的 Email',
            'intro' => '請點擊下方按鈕驗證你的 Email，連結 60 分鐘內有效。',
            'action' => '驗證 Email',
            'outro' => '如果你沒有註冊 GatherConf，請忽略這封信。',
        ],
        'reset' => [
            'subject' => '重設你的 GatherConf 密碼',
            'intro' => '請點擊下方按鈕重設密碼，連結 60 分鐘內有效。',
            'action' => '重設密碼',
            'outro' => '如果不是你要求的，請忽略這封信，密碼不會被更動。',
        ],
    ],
];
