<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommunityMessageReceivedNotification extends Notification
{
    public function __construct(public string $communityName, public string $url) {}

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
            ->subject(__('community-messages.mail.subject'))
            ->line(__('community-messages.mail.intro', ['community' => $this->communityName]))
            ->action(__('community-messages.mail.action'), $this->url)
            ->line(__('community-messages.mail.outro'));
    }
}
