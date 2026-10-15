<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DriftBottleRepliedNotification extends Notification
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
            ->subject(__('bottles.mail.replied.subject'))
            ->line(__('bottles.mail.replied.intro'))
            ->action(__('bottles.mail.replied.action'), $this->url)
            ->line(__('bottles.mail.replied.outro'));
    }
}
