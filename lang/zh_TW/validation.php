<?php

return [
    'required' => ':attribute 為必填。',
    'email' => ':attribute 必須是有效的 Email。',
    'confirmed' => ':attribute 與確認欄位不一致。',
    'unique' => '這個 :attribute 已經被使用。',
    'max' => [
        'string' => ':attribute 不可超過 :max 個字元。',
    ],
    'min' => [
        'string' => ':attribute 至少需要 :min 個字元。',
    ],
    'password' => [
        'letters' => ':attribute 必須包含至少一個字母。',
        'mixed' => ':attribute 必須包含大小寫字母。',
        'numbers' => ':attribute 必須包含至少一個數字。',
        'symbols' => ':attribute 必須包含至少一個符號。',
        'uncompromised' => '這個 :attribute 曾出現在外洩資料中，請換一個。',
    ],
    'attributes' => [
        'name' => '暱稱',
        'email' => 'Email',
        'password' => '密碼',
        'organization' => '所屬社群或公司',
        'body' => '訊息',
        'current_password' => '目前的密碼',
    ],
];
