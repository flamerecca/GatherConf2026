<?php

namespace Tests\Feature\CommunityMessages;

use App\Models\CommunityDirectory;
use App\Models\CommunityMessage;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityMessageModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_community_has_a_unique_slug(): void
    {
        $slugs = collect(config('communities'))->pluck('slug');

        $this->assertCount(count(config('communities')), $slugs->filter());
        $this->assertSame($slugs->count(), $slugs->unique()->count());
        $slugs->each(fn (string $slug) => $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug));
    }

    public function test_the_directory_finds_a_community_by_slug(): void
    {
        $community = CommunityDirectory::find('gdg-taoyuan');

        $this->assertSame('GDG 桃園', CommunityDirectory::name($community, 'zh_TW'));
        $this->assertNull(CommunityDirectory::find('nope'));
    }

    public function test_a_new_message_is_pending_and_belongs_to_the_member(): void
    {
        $member = Member::factory()->create();

        $message = CommunityMessage::factory()->for($member)->create();

        $this->assertSame('pending', $message->fresh()->status);
        $this->assertTrue($message->member->is($member));
        $this->assertSame(1, $member->communityMessages()->count());
    }

    public function test_mark_as_records_the_handler_and_time(): void
    {
        $message = CommunityMessage::factory()->create();

        $message->markAs(CommunityMessage::STATUS_FORWARDED, 7);

        $fresh = $message->fresh();
        $this->assertSame('forwarded', $fresh->status);
        $this->assertSame(7, $fresh->handled_by);
        $this->assertNotNull($fresh->handled_at);
    }

    public function test_reopening_clears_the_handler(): void
    {
        $message = CommunityMessage::factory()->create(['status' => 'closed', 'handled_by' => 7, 'handled_at' => now()]);

        $message->markAs(CommunityMessage::STATUS_PENDING, 7);

        $fresh = $message->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->handled_by);
        $this->assertNull($fresh->handled_at);
    }

    public function test_sent_today_counts_only_the_rolling_24_hours_of_that_member(): void
    {
        $member = Member::factory()->create();
        CommunityMessage::factory()->count(2)->for($member)->create();
        CommunityMessage::factory()->for($member)->create(['created_at' => now()->subDays(2)]);
        CommunityMessage::factory()->create();

        $this->assertSame(2, CommunityMessage::sentTodayBy($member));
    }

    public function test_deleting_a_member_deletes_their_messages(): void
    {
        $message = CommunityMessage::factory()->create();

        $message->member->delete();

        $this->assertNull(CommunityMessage::query()->find($message->id));
    }

    public function test_the_pending_scope(): void
    {
        CommunityMessage::factory()->create();
        CommunityMessage::factory()->create(['status' => 'closed']);

        $this->assertSame(1, CommunityMessage::query()->pending()->count());
    }
}
