<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyMemberEmailNotification extends Notification
{
    public function __construct()
    {
        $this->locale(app()->getLocale());
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('member.mail.verify.subject'))
            ->line(__('member.mail.verify.intro'))
            ->action(__('member.mail.verify.action'), $this->verificationUrl($notifiable))
            ->line(__('member.mail.verify.outro'));
    }

    protected function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'member.verification.verify'.($this->locale === 'en' ? '.en' : ''),
            now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
        );
    }
}
