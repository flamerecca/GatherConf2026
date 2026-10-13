<?php

return [
    'login' => [
        'title' => 'Log in | GatherConf 2026',
        'description' => 'Log in as a GatherConf member.',
        'heading' => 'Log in',
        'password_heading' => 'Log in with your password',
        'submit' => 'Log in',
        'remember' => 'Remember me',
        'forgot' => 'Forgot your password?',
        'register_prompt' => 'No account yet?',
        'register_link' => 'Sign up',
        'link_heading' => 'Or get a login link',
        'link_intro' => 'Enter your email and we will send you a one-time login link, no password needed.',
        'link_submit' => 'Send login link',
        'link_sent' => 'If this email is registered, a login link has been sent. Please check your inbox.',
    ],
    'center' => [
        'title' => 'Member center | GatherConf 2026',
        'heading' => 'Member center',
        'welcome' => 'Hi, :name',
        'organization' => 'Community or company',
        'not_set' => 'Not set',
        'logout' => 'Log out',
    ],
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
