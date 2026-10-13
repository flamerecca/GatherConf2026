<?php

return [
    'register' => [
        'title' => 'Sign up | GatherConf 2026',
        'description' => 'Sign up as a GatherConf member.',
        'heading' => 'Sign up',
        'submit' => 'Sign up',
        'have_account' => 'Already have an account?',
        'login_link' => 'Log in',
    ],
    'back' => 'Back to home',
    'switch_label' => '中文',
    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'organization' => 'Community or company, optional',
    ],
    'errors' => [
        'failed' => 'The email or password is incorrect.',
        'inactive' => 'This account has been deactivated. Please contact us if you have questions.',
        'link_invalid' => 'This login link is no longer valid. Please request a new one.',
    ],
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
