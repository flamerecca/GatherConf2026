<?php

namespace Tests\Feature\Messages;

use App\Models\Member;
use App\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewMessageNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mail_names_the_sender_and_links_to_the_conversation_without_the_body(): void
    {
        $sender = Member::factory()->create(['name' => '王小明']);
        $recipient = Member::factory()->create();

        app()->setLocale('zh_TW');
        $mail = (new NewMessageNotification($sender, 'https://example.test/messages/1'))->toMail($recipient);

        $this->assertSame('你收到一則新訊息', $mail->subject);
        $this->assertSame('https://example.test/messages/1', $mail->actionUrl);
        $this->assertStringContainsString('王小明', implode(' ', $mail->introLines));
        $this->assertSame(['mail'], (new NewMessageNotification($sender, 'x'))->via($recipient));
    }

    public function test_the_english_copy_is_used_for_the_english_locale(): void
    {
        $sender = Member::factory()->create(['name' => 'Alice']);

        app()->setLocale('en');
        $mail = (new NewMessageNotification($sender, 'https://example.test/en/messages/1'))->toMail($sender);

        $this->assertSame('You have a new message', $mail->subject);
        $this->assertSame('View message', $mail->actionText);
    }
}
