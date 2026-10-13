<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberLoginLinkNotification extends Notification
{
    public function __construct(public string $url) {}

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
            ->subject(__('member.mail.login_link.subject'))
            ->line(__('member.mail.login_link.intro'))
            ->action(__('member.mail.login_link.action'), $this->url)
            ->line(__('member.mail.login_link.outro'));
    }
}
