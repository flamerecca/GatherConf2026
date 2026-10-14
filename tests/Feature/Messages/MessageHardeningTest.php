<?php

namespace Tests\Feature\Messages;

use App\Models\Friendship;
use App\Models\Member;
use App\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class MessageHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_non_string_body_is_a_validation_error_not_a_server_error(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $friendship = Friendship::factory()->between($a, $b)->accepted()->create();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => ['x']])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_unverified_members_cannot_probe_ids_through_route_binding(): void
    {
        $unverified = Member::factory()->unverified()->create();
        $real = Friendship::factory()->accepted()->create();

        $this->actingAs($unverified, 'member')->get('/messages/999999')->assertRedirect('/email/verify');
        $this->actingAs($unverified, 'member')->get('/messages/'.$real->id)->assertRedirect('/email/verify');
        $this->actingAs($unverified, 'member')->post('/friends/999999/accept')->assertRedirect('/email/verify');
        $this->actingAs($unverified, 'member')->post('/friends/999999/block')->assertRedirect('/email/verify');
    }

    public function test_deactivated_members_cannot_probe_ids_through_route_binding(): void
    {
        $inactive = Member::factory()->inactive()->create();

        $this->actingAs($inactive, 'member')->get('/messages/999999')->assertRedirect('/login');
    }

    public function test_a_failed_reminder_does_not_use_up_the_throttle_slot(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $friendship = Friendship::factory()->between($a, $b)->accepted()->create();

        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new \RuntimeException('smtp down');
            }

            public function __toString(): string
            {
                return 'failing';
            }
        });
        config(['mail.default' => 'failing', 'mail.mailers.failing' => ['transport' => 'failing']]);

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'first'])->assertRedirect();

        Notification::fake();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'second'])->assertRedirect();

        Notification::assertSentToTimes($b, NewMessageNotification::class, 1);
    }
}
