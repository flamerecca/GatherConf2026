<?php

namespace App\Notifications;

use App\Models\Member;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification
{
    public function __construct(public Member $sender, public string $url) {}

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
            ->subject(__('member.mail.message.subject'))
            ->line(__('member.mail.message.intro', ['name' => $this->sender->name]))
            ->action(__('member.mail.message.action'), $this->url)
            ->line(__('member.mail.message.outro'));
    }
}
