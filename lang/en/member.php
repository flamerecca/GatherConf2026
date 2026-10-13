<?php

return [
    'mail' => [
        'login_link' => [
            'subject' => 'Your GatherConf login link',
            'intro' => 'Click the button below to log in. The link is valid for 15 minutes and can be used once.',
            'action' => 'Log in to GatherConf',
            'outro' => 'If you did not request this, you can ignore this email. Nothing will change.',
        ],
        'verify' => [
            'subject' => 'Verify your email',
            'intro' => 'Click the button below to verify your email. The link is valid for 60 minutes.',
            'action' => 'Verify email',
            'outro' => 'If you did not sign up for GatherConf, you can ignore this email.',
        ],
        'reset' => [
            'subject' => 'Reset your GatherConf password',
            'intro' => 'Click the button below to reset your password. The link is valid for 60 minutes.',
            'action' => 'Reset password',
            'outro' => 'If you did not request this, you can ignore this email. Your password will not change.',
        ],
    ],
];
