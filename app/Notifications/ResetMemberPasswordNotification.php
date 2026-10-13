<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetMemberPasswordNotification extends Notification
{
    public function __construct(public string $token)
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
        $url = route('member.password.reset'.($this->locale === 'en' ? '.en' : ''), [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(__('member.mail.reset.subject'))
            ->line(__('member.mail.reset.intro'))
            ->action(__('member.mail.reset.action'), $url)
            ->line(__('member.mail.reset.outro'));
    }
}
