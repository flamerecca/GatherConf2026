<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DriftBottleReceivedNotification extends Notification
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
            ->subject(__('bottles.mail.received.subject'))
            ->line(__('bottles.mail.received.intro'))
            ->action(__('bottles.mail.received.action'), $this->url)
            ->line(__('bottles.mail.received.outro'));
    }
}
