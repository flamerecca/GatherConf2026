# 會員短訊階段二實作計畫

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 讓互為好友的會員可以互傳純文字短訊，有已讀狀態、未讀數與不含內容的 Email 提醒，並讓後台管理者可以唯讀審閱好友關係與訊息。

**Architecture:** 新增 `messages` 資料表與 `Message` 模型，傳送資格、已讀與未讀數的規則集中在 `Message` 與 `Member`。前台新增一個 `MessageController` 與對話頁，沿用階段一的雙語路由與 `member.verified` 路由群組。Email 提醒以獨立的 `NewMessageNotification` 同步寄送，節流使用 `RateLimiter`，失敗時以 `sendQuietly` 吞掉。後台以兩個唯讀的 Filament 資源呈現好友關係與訊息。

**Tech Stack:** PHP 8.5、Laravel 13、Filament 4.12、PHPUnit 12、SQLite。

**Spec:** `docs/superpowers/specs/2026-10-14-friends-and-messages-design.md`，本計畫實作其中第 4.5 到 4.7、5.4、6、7、8 節與第 10、11 節的階段二。階段一已完成並合併，計畫為 `docs/superpowers/plans/2026-10-14-friends-stage-1.md`。

## Global Constraints

- 不新增任何 composer 依賴，不建立新的 `app` 底下的基礎資料夾。
- 模型使用 `#[Fillable([...])]` 與 `#[Hidden([...])]` 屬性，見 `.ai/rules/models.md`。
- 所有檔案以 `php artisan make:` 指令建立，並傳入 `--no-interaction`。
- 所有測試使用 PHPUnit 類別，使用 `RefreshDatabase`，建立資料一律用 factory，前台頁面測試在 `setUp()` 呼叫 `$this->withoutVite()`。
- 路由命名：中文版 `member.<name>`，英文版加 `.en`，英文路徑加 `/en`，位於 `auth:member` 與 `member.verified` 群組內。
- 訊息為純文字，去除前後空白後 1 到 1000 個字元，一律以 Blade 跳脫輸出，不解析連結，不使用 `{!! !!}`。
- 每位寄件者每分鐘最多 20 則，超過回應 429。
- 只有 `accepted` 的好友，且雙方皆啟用、任何一方都沒有封鎖對方時，才能傳送或閱讀對話，否則一律回應 404。
- Email 提醒不含訊息內容，同一寄件者對同一收件者 15 分鐘內最多一封，收件者停用時不寄，寄信失敗不得讓傳送失敗。
- 前台文案放入 `lang/zh_TW/member.php` 與 `lang/en/member.php`，台灣用語，中英文之間留半形空格，中文句中不使用括號補充說明。
- 後台的兩個資源唯讀，沒有建立、編輯與刪除，也沒有任何傳訊動作。
- 每次修改 PHP 檔案後，在 commit 前執行 `vendor/bin/pint --dirty --format agent`。
- 每次更新測試後執行該單一測試，不先跑整個測試套件。

## Review Focus

1. 非好友、被封鎖、對方停用或自己被停用時，對話頁與傳送都回應 404，POST 也不可寫入，Task 3 測試。
2. 訊息內容含 HTML 與換行時，畫面必須跳脫並保留換行，空白、超過 1000 個多位元組字元都被拒絕，Task 3 測試。
3. 開啟對話只會把「別人寄給我」的訊息標為已讀，不會動到我寄出的訊息，Task 2 與 Task 3 測試。
4. 未讀數不得計入已封鎖、已停用或已不是好友的寄件者，Task 1 與 Task 4 測試。
5. Email 提醒的內容絕不能含訊息本文，15 分鐘內只寄一封，Task 3 測試。
6. 移除好友後對話消失、重新成為好友後歷史回來，Task 3 測試。
7. 後台唯讀且只有名單內的管理者可進入，會員進不去，Task 5 與 Task 6 測試。

---

## File Structure

| 檔案 | 責任 |
| :---- | :---- |
| `database/migrations/*_create_messages_table.php` | 訊息資料表 |
| `app/Models/Message.php`、`database/factories/MessageFactory.php` | 傳送、已讀與對話串查詢 |
| `app/Models/Member.php` | 修改，傳訊資格與未讀數 |
| `app/Notifications/NewMessageNotification.php` | 新訊息提醒信 |
| `app/Http/Controllers/Member/MessageController.php` | 對話頁與傳送 |
| `app/Http/Controllers/Member/FriendController.php`、`CenterController.php` | 修改，未讀數與入口 |
| `app/Providers/AppServiceProvider.php`、`routes/web.php` | 限流與路由 |
| `resources/views/member/messages.blade.php`、`friends.blade.php`、`center.blade.php` | 頁面 |
| `lang/zh_TW/member.php`、`lang/en/member.php`、`lang/zh_TW/validation.php` | 文案 |
| `app/Filament/Resources/Friendships/*`、`app/Filament/Resources/Messages/*` | 後台唯讀資源 |
| `app/Filament/Resources/Members/MemberResource.php` | 修改，加入導覽群組 |
| `tests/Feature/Messages/*.php` | 對應各任務的測試 |

---

### Task 1: 資料層與傳訊資格

**Files:**
- Create: `app/Models/Message.php`、`database/factories/MessageFactory.php`、`database/migrations/*_create_messages_table.php`
- Modify: `app/Models/Member.php`
- Test: `tests/Feature/Messages/MessageModelTest.php`

**Interfaces:**
- Consumes: 階段一的 `Friendship`、`MemberBlock`、`Member::blockedPairMemberIds()`、`isFriendWith()`、`hasBlocked()`、`isBlockedBy()`。
- Produces:
  - `Message::MAX_LENGTH = 1000`、`Message::PAGE_SIZE = 50`。
  - `Message::sender()`、`recipient()`、`scopeBetween(Member, Member)`。
  - `Message::sendFrom(Member $sender, Member $recipient, string $body): ?Message`，不可傳送時回傳 `null`。
  - `Message::markRead(Member $reader, Member $other): int`，只更新寄給 `reader` 的未讀訊息，回傳筆數。
  - `Message::latestPage(Member $a, Member $b, ?int $beforeId = null): Collection`，由舊到新，最多 `PAGE_SIZE` 則。
  - `Member::canMessage(Member $other): bool`、`messageableFriendIds(): Collection`、`unreadMessageCount(): int`、`unreadMessageCountFrom(Member $other): int`。
  - `MessageFactory`：`from(Member $sender, Member $recipient)`、`read()`。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:model Message -mf --no-interaction
php artisan make:test Messages/MessageModelTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Messages;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageModelTest extends TestCase
{
    use RefreshDatabase;

    private function friends(): array
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();

        return [$a, $b];
    }

    public function test_friends_can_message_each_other(): void
    {
        [$a, $b] = $this->friends();

        $this->assertTrue($a->canMessage($b));
        $this->assertTrue($b->canMessage($a));
    }

    public function test_strangers_pending_blocked_inactive_and_self_cannot_message(): void
    {
        [$a, $b] = $this->friends();
        $stranger = Member::factory()->create();
        $pending = Member::factory()->create();
        Friendship::factory()->between($a, $pending)->create();

        $this->assertFalse($a->canMessage($stranger));
        $this->assertFalse($a->canMessage($pending));
        $this->assertFalse($a->canMessage($a));

        $b->update(['is_active' => false]);
        $this->assertFalse($a->fresh()->canMessage($b->fresh()));
        $this->assertFalse($b->fresh()->canMessage($a->fresh()));

        $b->update(['is_active' => true]);
        MemberBlock::factory()->create(['blocker_id' => $a->id, 'blocked_id' => $b->id]);
        $this->assertFalse($a->canMessage($b));
        $this->assertFalse($b->canMessage($a));
    }

    public function test_send_from_stores_the_message_unread(): void
    {
        [$a, $b] = $this->friends();

        $message = Message::sendFrom($a, $b, 'hello');

        $this->assertSame('hello', $message->body);
        $this->assertSame($a->id, $message->sender_id);
        $this->assertSame($b->id, $message->recipient_id);
        $this->assertNull($message->read_at);
    }

    public function test_send_from_returns_null_when_not_allowed(): void
    {
        $a = Member::factory()->create();
        $stranger = Member::factory()->create();

        $this->assertNull(Message::sendFrom($a, $stranger, 'hi'));
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_mark_read_only_touches_messages_sent_to_the_reader(): void
    {
        [$a, $b] = $this->friends();
        $toA = Message::factory()->from($b, $a)->create();
        $toB = Message::factory()->from($a, $b)->create();

        $this->assertSame(1, Message::markRead($a, $b));

        $this->assertNotNull($toA->fresh()->read_at);
        $this->assertNull($toB->fresh()->read_at);
        $this->assertSame(0, Message::markRead($a, $b));
    }

    public function test_latest_page_is_oldest_first_and_capped(): void
    {
        [$a, $b] = $this->friends();
        Message::factory()->from($a, $b)->count(55)->create();

        $page = Message::latestPage($a, $b);

        $this->assertCount(Message::PAGE_SIZE, $page);
        $this->assertSame($page->sortBy('id')->pluck('id')->all(), $page->pluck('id')->all());
        $this->assertSame(Message::query()->max('id'), $page->last()->id);
    }

    public function test_latest_page_can_load_older_messages(): void
    {
        [$a, $b] = $this->friends();
        Message::factory()->from($a, $b)->count(55)->create();
        $first = Message::latestPage($a, $b);

        $older = Message::latestPage($a, $b, $first->first()->id);

        $this->assertCount(5, $older);
        $this->assertTrue($older->last()->id < $first->first()->id);
    }

    public function test_conversation_includes_both_directions_only_for_the_pair(): void
    {
        [$a, $b] = $this->friends();
        $c = Member::factory()->create();
        Friendship::factory()->between($a, $c)->accepted()->create();
        Message::factory()->from($a, $b)->create();
        Message::factory()->from($b, $a)->create();
        Message::factory()->from($a, $c)->create();

        $this->assertCount(2, Message::latestPage($a, $b));
        $this->assertCount(2, Message::latestPage($b, $a));
    }

    public function test_unread_count_only_counts_current_messageable_friends(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create();
        $blocked = Member::factory()->create();
        $inactive = Member::factory()->create();
        $removed = Member::factory()->create();
        $stranger = Member::factory()->create();
        foreach ([$friend, $blocked, $inactive] as $other) {
            Friendship::factory()->between($me, $other)->accepted()->create();
        }
        foreach ([$friend, $blocked, $inactive, $removed, $stranger] as $sender) {
            Message::factory()->from($sender, $me)->create();
        }
        Message::factory()->from($friend, $me)->read()->create();
        Message::factory()->from($me, $friend)->create();
        MemberBlock::factory()->create(['blocker_id' => $me->id, 'blocked_id' => $blocked->id]);
        $inactive->update(['is_active' => false]);

        $this->assertSame(1, $me->fresh()->unreadMessageCount());
        $this->assertSame(1, $me->fresh()->unreadMessageCountFrom($friend));
        $this->assertSame(0, $me->fresh()->unreadMessageCountFrom($blocked));
    }

    public function test_messages_are_removed_with_the_member(): void
    {
        [$a, $b] = $this->friends();
        Message::factory()->from($a, $b)->create();

        $a->delete();

        $this->assertDatabaseCount('messages', 0);
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Messages/MessageModelTest.php`
Expected: FAIL，資料表不存在。

- [ ] **Step 4: 實作 migration**

```php
public function up(): void
{
    Schema::create('messages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sender_id')->constrained('members')->cascadeOnDelete();
        $table->foreignId('recipient_id')->constrained('members')->cascadeOnDelete();
        $table->text('body');
        $table->timestamp('read_at')->nullable();
        $table->timestamp('created_at')->useCurrent();

        $table->index(['recipient_id', 'read_at']);
        $table->index(['sender_id', 'recipient_id', 'id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('messages');
}
```

- [ ] **Step 5: 實作模型**

`app/Models/Message.php`：

```php
<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sender_id', 'recipient_id', 'body', 'read_at'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const MAX_LENGTH = 1000;

    public const PAGE_SIZE = 50;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'recipient_id');
    }

    public function scopeBetween(Builder $query, Member $a, Member $b): Builder
    {
        return $query->where(function (Builder $query) use ($a, $b): void {
            $query->where(function (Builder $query) use ($a, $b): void {
                $query->where('sender_id', $a->getKey())->where('recipient_id', $b->getKey());
            })->orWhere(function (Builder $query) use ($a, $b): void {
                $query->where('sender_id', $b->getKey())->where('recipient_id', $a->getKey());
            });
        });
    }

    public static function sendFrom(Member $sender, Member $recipient, string $body): ?self
    {
        if (! $sender->canMessage($recipient)) {
            return null;
        }

        return static::query()->create([
            'sender_id' => $sender->getKey(),
            'recipient_id' => $recipient->getKey(),
            'body' => $body,
        ]);
    }

    public static function markRead(Member $reader, Member $other): int
    {
        return static::query()
            ->where('recipient_id', $reader->getKey())
            ->where('sender_id', $other->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @return Collection<int, Message>
     */
    public static function latestPage(Member $a, Member $b, ?int $beforeId = null): Collection
    {
        $query = static::query()
            ->between($a, $b)
            ->orderByDesc('id')
            ->limit(self::PAGE_SIZE);

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        return $query->get()->reverse()->values();
    }
}
```

`database/factories/MessageFactory.php`：

```php
<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_id' => Member::factory(),
            'recipient_id' => Member::factory(),
            'body' => fake()->sentence(),
            'read_at' => null,
        ];
    }

    public function from(Member $sender, Member $recipient): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_id' => $sender->getKey(),
            'recipient_id' => $recipient->getKey(),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => ['read_at' => now()]);
    }
}
```

- [ ] **Step 6: 修改 `Member`**

在 `canBlock()` 之後加入：

```php
public function canMessage(Member $other): bool
{
    return ! $this->is($other)
        && $this->is_active
        && $other->is_active
        && $this->isFriendWith($other)
        && ! $this->hasBlocked($other)
        && ! $this->isBlockedBy($other);
}

/**
 * Ids of active, unblocked members who are accepted friends.
 *
 * @return Collection<int, int>
 */
public function messageableFriendIds(): Collection
{
    $blocked = $this->blockedPairMemberIds();

    return Friendship::query()
        ->accepted()
        ->involving($this)
        ->with(['low', 'high'])
        ->get()
        ->map(fn (Friendship $friendship) => $friendship->otherMember($this))
        ->filter(fn (Member $other) => $other->is_active && ! $blocked->contains($other->getKey()))
        ->map(fn (Member $other) => $other->getKey())
        ->values();
}

public function unreadMessageCount(): int
{
    return Message::query()
        ->where('recipient_id', $this->getKey())
        ->whereNull('read_at')
        ->whereIn('sender_id', $this->messageableFriendIds())
        ->count();
}

public function unreadMessageCountFrom(Member $other): int
{
    if (! $this->messageableFriendIds()->contains($other->getKey())) {
        return 0;
    }

    return Message::query()
        ->where('recipient_id', $this->getKey())
        ->where('sender_id', $other->getKey())
        ->whereNull('read_at')
        ->count();
}
```

- [ ] **Step 7: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Messages/MessageModelTest.php`
Expected: PASS，10 個測試。

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app database tests/Feature/Messages
git commit -m "Add messages with send eligibility, read state and unread counts"
```

---

### Task 2: 新訊息提醒信

**Files:**
- Create: `app/Notifications/NewMessageNotification.php`
- Modify: `lang/zh_TW/member.php`、`lang/en/member.php`
- Test: `tests/Feature/Messages/NewMessageNotificationTest.php`

**Interfaces:**
- Produces: `new NewMessageNotification(Member $sender, string $url)`，只有 `mail` 通道，內容不含訊息本文；公開屬性 `$sender`、`$url`。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:notification NewMessageNotification --no-interaction
php artisan make:test Messages/NewMessageNotificationTest --phpunit --no-interaction
```

- [ ] **Step 2: 文案**

在 `lang/zh_TW/member.php` 的 `mail` 陣列加入：

```php
'message' => [
    'subject' => '你收到一則新訊息',
    'intro' => ':name 在 GatherConf 傳了一則新訊息給你。',
    'action' => '查看訊息',
    'outro' => '為了保護隱私，信中不會顯示訊息內容。',
],
```

在 `lang/en/member.php` 的 `mail` 陣列加入：

```php
'message' => [
    'subject' => 'You have a new message',
    'intro' => ':name sent you a new message on GatherConf.',
    'action' => 'View message',
    'outro' => 'To protect your privacy, the message content is not shown in this email.',
],
```

- [ ] **Step 3: 寫失敗的測試**

```php
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
```

- [ ] **Step 4: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Messages/NewMessageNotificationTest.php`
Expected: FAIL，建構子不接受引數。

- [ ] **Step 5: 實作**

`app/Notifications/NewMessageNotification.php`：

```php
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
```

- [ ] **Step 6: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Messages/NewMessageNotificationTest.php`
Expected: PASS，2 個測試。

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications lang tests/Feature/Messages/NewMessageNotificationTest.php
git commit -m "Add new message notification without the message body"
```

---

### Task 3: 對話頁與傳送

**Files:**
- Create: `app/Http/Controllers/Member/MessageController.php`、`resources/views/member/messages.blade.php`
- Modify: `routes/web.php`、`app/Providers/AppServiceProvider.php`、`lang/zh_TW/member.php`、`lang/en/member.php`、`lang/zh_TW/validation.php`
- Test: `tests/Feature/Messages/MessageConversationTest.php`

**Interfaces:**
- Consumes: Task 1 的 `Message`、`canMessage`；Task 2 的 `NewMessageNotification`；階段一的 `Friendship`、`sendQuietly`。
- Produces:
  - `GET /messages/{friendship}` 名稱 `member.messages.show`，支援 `?before={id}`。
  - `POST /messages/{friendship}` 名稱 `member.messages.store`，限流器 `member-message`。
  - 英文版加 `.en`。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:controller Member/MessageController --no-interaction
php artisan make:test Messages/MessageConversationTest --phpunit --no-interaction
```

`MessageController` 先改成 `extends MemberController` 的空類別。

- [ ] **Step 2: 文案**

`lang/zh_TW/member.php` 新增：

```php
'messages' => [
    'title' => '與 :name 的訊息 | 共聚年會 GatherConf 2026',
    'heading' => '與 :name 的訊息',
    'privacy' => '訊息可能因管理與檢舉處理而由管理者檢視。',
    'empty' => '還沒有訊息，傳一則打聲招呼吧。',
    'placeholder' => '輸入訊息，最多 1000 個字',
    'send' => '傳送',
    'read' => '已讀',
    'older' => '載入更早的訊息',
    'me' => '我',
    'back' => '回到我的好友',
],
```

`lang/en/member.php` 新增：

```php
'messages' => [
    'title' => 'Messages with :name | GatherConf 2026',
    'heading' => 'Messages with :name',
    'privacy' => 'Messages may be viewed by administrators for moderation and reports.',
    'empty' => 'No messages yet. Say hello.',
    'placeholder' => 'Type a message, up to 1000 characters',
    'send' => 'Send',
    'read' => 'Read',
    'older' => 'Load earlier messages',
    'me' => 'Me',
    'back' => 'Back to my friends',
],
```

`lang/zh_TW/validation.php` 的 `attributes` 加入 `'body' => '訊息',`。

- [ ] **Step 3: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Messages;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use App\Models\Message;
use App\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class MessageConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array{0: Member, 1: Member, 2: Friendship}
     */
    private function friends(): array
    {
        $a = Member::factory()->create(['name' => '甲']);
        $b = Member::factory()->create(['name' => '乙']);
        $friendship = Friendship::factory()->between($a, $b)->accepted()->create();

        return [$a, $b, $friendship];
    }

    public function test_conversation_shows_messages_in_order_with_the_privacy_notice(): void
    {
        [$a, $b, $friendship] = $this->friends();
        Message::factory()->from($a, $b)->create(['body' => '第一則']);
        Message::factory()->from($b, $a)->create(['body' => '第二則']);

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)
            ->assertOk()
            ->assertSeeInOrder(['第一則', '第二則'])
            ->assertSee('訊息可能因管理與檢舉處理而由管理者檢視')
            ->assertSee('noindex', false);

        $this->actingAs($a, 'member')->get('/en/messages/'.$friendship->id)
            ->assertOk()
            ->assertSee('Messages may be viewed by administrators')
            ->assertDontSee('訊息可能因管理');
    }

    public function test_opening_the_conversation_marks_only_received_messages_as_read(): void
    {
        [$a, $b, $friendship] = $this->friends();
        $received = Message::factory()->from($b, $a)->create();
        $sent = Message::factory()->from($a, $b)->create();

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertOk();

        $this->assertNotNull($received->fresh()->read_at);
        $this->assertNull($sent->fresh()->read_at);
    }

    public function test_the_sender_sees_a_read_marker_after_the_recipient_opens_it(): void
    {
        [$a, $b, $friendship] = $this->friends();
        Message::factory()->from($a, $b)->read()->create(['body' => '已被讀']);

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertSee('已讀');
    }

    public function test_only_the_latest_page_is_shown_with_a_link_to_older_messages(): void
    {
        [$a, $b, $friendship] = $this->friends();
        Message::factory()->from($a, $b)->count(55)->create();

        $first = Message::latestPage($a, $b)->first();

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)
            ->assertSee('?before='.$first->id, false);

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id.'?before='.$first->id)
            ->assertOk()
            ->assertDontSee('?before=', false);
    }

    public function test_non_participants_strangers_and_pending_get_404(): void
    {
        [$a, $b, $friendship] = $this->friends();
        $outsider = Member::factory()->create();
        $pending = Friendship::factory()->between($a, $outsider)->create();

        $this->actingAs($outsider, 'member')->get('/messages/'.$friendship->id)->assertNotFound();
        $this->actingAs($outsider, 'member')->post('/messages/'.$friendship->id, ['body' => 'x'])->assertNotFound();
        $this->actingAs($a, 'member')->get('/messages/'.$pending->id)->assertNotFound();
        $this->actingAs($a, 'member')->post('/messages/'.$pending->id, ['body' => 'x'])->assertNotFound();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blocked_or_inactive_friends_get_404_and_nothing_is_written(): void
    {
        [$a, $b, $friendship] = $this->friends();
        MemberBlock::factory()->create(['blocker_id' => $b->id, 'blocked_id' => $a->id]);

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertNotFound();
        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'hi'])->assertNotFound();
        $this->assertDatabaseCount('messages', 0);

        MemberBlock::query()->delete();
        $b->update(['is_active' => false]);

        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertNotFound();
        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'hi'])->assertNotFound();
    }

    public function test_sending_stores_the_message_and_redirects_back(): void
    {
        Notification::fake();
        [$a, $b, $friendship] = $this->friends();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => "  你好\n第二行  "])
            ->assertRedirect('/messages/'.$friendship->id);

        $message = Message::query()->firstOrFail();
        $this->assertSame("你好\n第二行", $message->body);
        $this->assertSame($a->id, $message->sender_id);
        $this->assertSame($b->id, $message->recipient_id);
    }

    public function test_english_send_redirects_to_the_english_conversation(): void
    {
        Notification::fake();
        [$a, , $friendship] = $this->friends();

        $this->actingAs($a, 'member')->post('/en/messages/'.$friendship->id, ['body' => 'hi'])
            ->assertRedirect('/en/messages/'.$friendship->id);
    }

    public function test_body_is_validated(): void
    {
        [$a, , $friendship] = $this->friends();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => '   '])->assertSessionHasErrors('body');
        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => str_repeat('字', 1001)])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_exactly_1000_multibyte_characters_are_accepted(): void
    {
        Notification::fake();
        [$a, , $friendship] = $this->friends();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => str_repeat('字', 1000)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_html_is_escaped_and_newlines_are_preserved(): void
    {
        [$a, $b, $friendship] = $this->friends();
        Message::factory()->from($b, $a)->create(['body' => "<script>alert(1)</script>\n<b>bold</b>"]);

        $page = $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page);
        $this->assertStringContainsString('whitespace-pre-line', $page);
    }

    public function test_sending_is_rate_limited_to_twenty_per_minute(): void
    {
        Notification::fake();
        [$a, , $friendship] = $this->friends();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => "m{$i}"])->assertRedirect();
        }

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'too many'])->assertStatus(429);
        $this->assertDatabaseCount('messages', 20);
    }

    public function test_the_reminder_email_has_no_body_and_is_throttled_per_conversation(): void
    {
        Notification::fake();
        [$a, $b, $friendship] = $this->friends();
        RateLimiter::clear('member-message-notify:'.$b->id.':'.$a->id);

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => '機密內容一']);
        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => '機密內容二']);

        Notification::assertSentToTimes($b, NewMessageNotification::class, 1);

        $mail = null;
        Notification::assertSentTo($b, NewMessageNotification::class, function (NewMessageNotification $notification) use ($b, &$mail) {
            $mail = $notification->toMail($b);

            return true;
        });

        $text = implode(' ', array_merge($mail->introLines, $mail->outroLines, [$mail->subject]));
        $this->assertStringNotContainsString('機密內容', $text);
        $this->assertStringContainsString('/messages/'.$friendship->id, $mail->actionUrl);
    }

    public function test_a_different_sender_is_throttled_separately(): void
    {
        Notification::fake();
        [$a, $b, $friendship] = $this->friends();
        $c = Member::factory()->create();
        $second = Friendship::factory()->between($c, $b)->accepted()->create();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'a']);
        $this->actingAs($c, 'member')->post('/messages/'.$second->id, ['body' => 'c']);

        Notification::assertSentToTimes($b, NewMessageNotification::class, 2);
    }

    public function test_a_mail_failure_does_not_fail_the_send(): void
    {
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
        [$a, , $friendship] = $this->friends();

        $this->actingAs($a, 'member')->post('/messages/'.$friendship->id, ['body' => 'hi'])
            ->assertRedirect('/messages/'.$friendship->id);

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_removing_the_friend_hides_the_conversation_and_re_adding_restores_history(): void
    {
        [$a, $b, $friendship] = $this->friends();
        Message::factory()->from($a, $b)->create(['body' => '舊訊息']);

        $friendship->removeBy($a);
        $this->actingAs($a, 'member')->get('/messages/'.$friendship->id)->assertNotFound();

        Friendship::factory()->between($a, $b)->accepted()->create();
        $new = Friendship::between($a, $b);

        $this->actingAs($b, 'member')->get('/messages/'.$new->id)->assertOk()->assertSee('舊訊息');
    }

    public function test_guests_and_unverified_members_are_redirected(): void
    {
        [, , $friendship] = $this->friends();

        $this->get('/messages/'.$friendship->id)->assertRedirect('/login');
        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/messages/'.$friendship->id)
            ->assertRedirect('/email/verify');
    }
}
```

- [ ] **Step 4: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Messages/MessageConversationTest.php`
Expected: FAIL，路由不存在。

- [ ] **Step 5: 限流器與路由**

`AppServiceProvider::boot()`，在 `member-invite` 之後加入：

```php
RateLimiter::for('member-message', fn (Request $request) => Limit::perMinute(20)
    ->by('member-message|'.($request->user('member')?->getKey() ?? $request->ip())));
```

`routes/web.php` 的 `use` 區加入 `use App\Http\Controllers\Member\MessageController;`，並在階段一的 `member.verified` 群組內、`member.friends.unblock` 之後加入：

```php
Route::get('/messages/{friendship}', [MessageController::class, 'show'])->name('member.messages.show'.$s);
Route::post('/messages/{friendship}', [MessageController::class, 'store'])->middleware('throttle:member-message')->name('member.messages.store'.$s);
```

- [ ] **Step 6: 實作控制器**

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\Message;
use App\Notifications\NewMessageNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class MessageController extends MemberController
{
    public function show(Request $request, Friendship $friendship): View
    {
        [$me, $friend] = $this->conversationFor($friendship);

        Message::markRead($me, $friend);

        $beforeId = $request->integer('before') ?: null;
        $messages = Message::latestPage($me, $friend, $beforeId);

        $hasOlder = $messages->isNotEmpty()
            && Message::query()->between($me, $friend)->where('id', '<', $messages->first()->id)->exists();

        return view('member.messages', [
            'me' => $me,
            'friend' => $friend,
            'friendship' => $friendship,
            'messages' => $messages,
            'olderBeforeId' => $hasOlder ? $messages->first()->id : null,
        ]);
    }

    public function store(Request $request, Friendship $friendship): RedirectResponse
    {
        [$me, $friend] = $this->conversationFor($friendship);

        $request->merge(['body' => trim((string) $request->input('body'))]);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.Message::MAX_LENGTH],
        ]);

        $message = Message::sendFrom($me, $friend, $data['body']);

        abort_if($message === null, 404);

        $this->sendQuietly(function () use ($me, $friend, $friendship): void {
            if (! $friend->is_active) {
                return;
            }

            $key = 'member-message-notify:'.$friend->getKey().':'.$me->getKey();

            if (RateLimiter::attempt($key, 1, fn () => true, 900)) {
                $friend->notify(
                    (new NewMessageNotification($me, route('member.messages.show', $friendship)))->locale('zh_TW'),
                );
            }
        });

        return redirect($this->localizedRoute('member.messages.show', ['friendship' => $friendship]));
    }

    /**
     * @return array{0: Member, 1: Member}
     */
    private function conversationFor(Friendship $friendship): array
    {
        $me = $this->guard()->user();

        abort_unless($friendship->isParticipant($me) && $friendship->isAccepted(), 404);

        $friend = $friendship->otherMember($me);

        abort_unless($me->canMessage($friend), 404);

        return [$me, $friend];
    }
}
```

- [ ] **Step 7: 實作頁面**

`resources/views/member/messages.blade.php`：

```blade
@extends('member.layout')

@php
    $zhUrl = route('member.messages.show', ['friendship' => $friendship]);
    $enUrl = route('member.messages.show.en', ['friendship' => $friendship]);
    $s = app()->getLocale() === 'en' ? '.en' : '';
@endphp

@section('route', 'member.friends')
@section('zh_url', $zhUrl)
@section('en_url', $enUrl)
@section('robots', 'noindex')
@section('title', __('member.messages.title', ['name' => $friend->name]))
@section('description', __('member.messages.title', ['name' => $friend->name]))
@section('heading', __('member.messages.heading', ['name' => $friend->name]))

@section('member_body')
    <p class="mt-4 text-xs text-ink/70">{{ __('member.messages.privacy') }}</p>

    @if ($olderBeforeId)
        <a href="{{ route('member.messages.show'.$s, ['friendship' => $friendship]) }}?before={{ $olderBeforeId }}"
           class="mt-6 inline-block text-sm font-medium text-moss underline underline-offset-4">
            {{ __('member.messages.older') }}
        </a>
    @endif

    <ol class="mt-6 space-y-3">
        @forelse ($messages as $message)
            @php($mine = $message->sender_id === $me->getKey())
            <li class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[85%] rounded-2xl border border-ink/15 p-3 {{ $mine ? 'bg-moss/15' : 'bg-parchment-dark' }}">
                    <p class="text-xs font-semibold text-ink/70">{{ $mine ? __('member.messages.me') : $friend->name }}</p>
                    <p class="mt-1 whitespace-pre-line break-words">{{ $message->body }}</p>
                    <p class="mt-1 text-xs text-ink/60">
                        {{ $message->created_at->format('Y-m-d H:i') }}
                        @if ($mine && $message->read_at)
                            · {{ __('member.messages.read') }}
                        @endif
                    </p>
                </div>
            </li>
        @empty
            <li class="text-sm text-ink/70">{{ __('member.messages.empty') }}</li>
        @endforelse
    </ol>

    <form method="POST" action="{{ route('member.messages.store'.$s, ['friendship' => $friendship]) }}"
          class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-4">
        @csrf
        <label for="body" class="sr-only">{{ __('member.messages.send') }}</label>
        <textarea id="body" name="body" rows="3" maxlength="{{ \App\Models\Message::MAX_LENGTH }}" required
                  placeholder="{{ __('member.messages.placeholder') }}"
                  class="w-full rounded-xl border border-ink/30 bg-parchment px-4 py-3 focus:border-moss focus:outline-none focus:ring-2 focus:ring-moss/40">{{ old('body') }}</textarea>
        @error('body')
            <p class="text-sm text-copper" role="alert">{{ $message }}</p>
        @enderror
        <x-member-submit>{{ __('member.messages.send') }}</x-member-submit>
    </form>

    <a href="{{ route('member.friends'.$s) }}" class="mt-6 inline-block text-sm font-medium text-moss underline underline-offset-4">
        {{ __('member.messages.back') }}
    </a>
@endsection
```

注意：`@error('body')` 內的 `$message` 是錯誤訊息，會遮蔽迴圈裡的 `$message`，但該區塊在迴圈之外，不會衝突。

- [ ] **Step 8: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Messages tests/Feature/Friends`
Expected: PASS。

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources routes lang tests/Feature/Messages/MessageConversationTest.php
git commit -m "Add the conversation page, sending, read state and reminder emails"
```

---

### Task 4: 入口與未讀數

**Files:**
- Modify: `app/Http/Controllers/Member/FriendController.php`、`CenterController.php`、`resources/views/member/friends.blade.php`、`center.blade.php`、`lang/zh_TW/member.php`、`lang/en/member.php`
- Test: `tests/Feature/Messages/MessageEntryPointsTest.php`

**Interfaces:**
- Consumes: Task 1 的 `unreadMessageCount`、`unreadMessageCountFrom`。
- Produces: 好友清單每位好友的「傳訊息」連結與未讀數，會員中心的未讀訊息數。

- [ ] **Step 1: 文案**

`lang/zh_TW/member.php` 的 `friends` 陣列加入 `'message' => '傳訊息',`，`center` 陣列加入 `'unread_messages' => '未讀訊息',`。`lang/en/member.php` 的 `friends` 陣列加入 `'message' => 'Message',`，`center` 陣列加入 `'unread_messages' => 'Unread messages',`。

- [ ] **Step 2: 寫失敗的測試**

```bash
php artisan make:test Messages/MessageEntryPointsTest --phpunit --no-interaction
```

```php
<?php

namespace Tests\Feature\Messages;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_friends_page_links_to_each_conversation_with_the_unread_count(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create(['name' => '好友甲']);
        $friendship = Friendship::factory()->between($me, $friend)->accepted()->create();
        Message::factory()->from($friend, $me)->count(3)->create();

        $this->actingAs($me, 'member')->get('/friends')
            ->assertSee(route('member.messages.show', $friendship), false)
            ->assertSee('data-unread="3"', false);

        $this->actingAs($me, 'member')->get('/en/friends')
            ->assertSee(route('member.messages.show.en', $friendship), false);
    }

    public function test_friends_page_does_not_link_to_conversations_with_blocked_members(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create();
        $friendship = Friendship::factory()->between($me, $friend)->accepted()->create();
        MemberBlock::factory()->create(['blocker_id' => $me->id, 'blocked_id' => $friend->id]);

        $this->actingAs($me, 'member')->get('/friends')
            ->assertDontSee(route('member.messages.show', $friendship), false);
    }

    public function test_member_center_shows_the_total_unread_count(): void
    {
        $me = Member::factory()->create();
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($me, $a)->accepted()->create();
        Friendship::factory()->between($me, $b)->accepted()->create();
        Message::factory()->from($a, $me)->count(2)->create();
        Message::factory()->from($b, $me)->create();
        Message::factory()->from($b, $me)->read()->create();

        $this->actingAs($me, 'member')->get('/member')->assertSee('data-unread-messages="3"', false);
    }

    public function test_opening_a_conversation_clears_its_unread_count(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create();
        $friendship = Friendship::factory()->between($me, $friend)->accepted()->create();
        Message::factory()->from($friend, $me)->count(2)->create();

        $this->actingAs($me, 'member')->get('/messages/'.$friendship->id)->assertOk();

        $this->actingAs($me, 'member')->get('/member')->assertSee('data-unread-messages="0"', false);
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Messages/MessageEntryPointsTest.php`
Expected: FAIL，頁面沒有連結與數字。

- [ ] **Step 4: 實作**

`FriendController::index` 的 `view()` 資料加入：

```php
'unreadByMember' => \App\Models\Message::query()
    ->where('recipient_id', $me->getKey())
    ->whereNull('read_at')
    ->selectRaw('sender_id, count(*) as total')
    ->groupBy('sender_id')
    ->pluck('total', 'sender_id'),
'messageableIds' => $me->messageableFriendIds(),
```

`CenterController::show` 的 `view()` 資料加入 `'unreadMessages' => $member->unreadMessageCount(),`。

`resources/views/member/friends.blade.php` 的「好友」區塊，把按鈕列的 `<div class="mt-3 flex flex-wrap gap-3">` 之後第一項加入：

```blade
@if ($messageableIds->contains($other->getKey()))
    <a href="{{ route('member.messages.show'.$s, ['friendship' => $friendship]) }}"
       data-unread="{{ $unreadByMember[$other->getKey()] ?? 0 }}"
       class="inline-flex items-center gap-2 rounded-full bg-moss px-6 py-3 font-semibold text-on-accent shadow transition hover:bg-ink">
        {{ __('member.friends.message') }}
        @if (($unreadByMember[$other->getKey()] ?? 0) > 0)
            <span class="rounded-full bg-parchment px-2 py-0.5 text-xs text-ink">{{ $unreadByMember[$other->getKey()] }}</span>
        @endif
    </a>
@endif
```

`resources/views/member/center.blade.php` 在好友入口連結之後加入：

```blade
<p data-unread-messages="{{ $unreadMessages }}" class="text-sm text-ink/80">
    {{ __('member.center.unread_messages') }}：{{ $unreadMessages }}
</p>
```

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Messages tests/Feature/Friends`
Expected: PASS。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources lang tests/Feature/Messages/MessageEntryPointsTest.php
git commit -m "Add conversation links and unread counts to the friends and center pages"
```

---

### Task 5: 後台唯讀的好友關係資源

**Files:**
- Create: `app/Filament/Resources/Friendships/FriendshipResource.php`、`Pages/ListFriendships.php`、`Pages/ViewFriendship.php`、`Tables/FriendshipsTable.php`、`Schemas/FriendshipInfolist.php`
- Modify: `app/Filament/Resources/Members/MemberResource.php`
- Test: `tests/Feature/Messages/FriendshipResourceTest.php`

**Interfaces:**
- Consumes: 階段一的 `Friendship`。
- Produces: 後台「好友關係」唯讀資源，頁面 `index` 與 `view`。

- [ ] **Step 1: 產生資源**

```bash
php artisan make:filament-resource Friendship --view --no-interaction
find app/Filament/Resources/Friendships -type f
```

刪除建立與編輯頁：

```bash
rm -f app/Filament/Resources/Friendships/Pages/CreateFriendship.php app/Filament/Resources/Friendships/Pages/EditFriendship.php
```

若產生的目錄或檔名與下方不同，以實際為準，並同步調整後續的 `use` 與測試。

- [ ] **Step 2: 寫失敗的測試**

```bash
php artisan make:test Messages/FriendshipResourceTest --phpunit --no-interaction
```

```php
<?php

namespace Tests\Feature\Messages;

use App\Filament\Resources\Friendships\FriendshipResource;
use App\Filament\Resources\Friendships\Pages\ListFriendships;
use App\Filament\Resources\Friendships\Pages\ViewFriendship;
use App\Models\Friendship;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FriendshipResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
    }

    public function test_list_shows_friendships(): void
    {
        $friendships = Friendship::factory()->count(3)->create();

        Livewire::test(ListFriendships::class)
            ->assertOk()
            ->assertCanSeeTableRecords($friendships);
    }

    public function test_list_can_search_by_member_name_and_email(): void
    {
        $alice = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com']);
        $bob = Member::factory()->create(['name' => 'Bob Lin', 'email' => 'bob@example.com']);
        $carol = Member::factory()->create(['name' => 'Carol', 'email' => 'carol@example.com']);
        $dave = Member::factory()->create(['name' => 'Dave', 'email' => 'dave@example.com']);
        $withAlice = Friendship::factory()->between($alice, $bob)->create();
        $other = Friendship::factory()->between($carol, $dave)->create();

        Livewire::test(ListFriendships::class)
            ->searchTable('Alice')
            ->assertCanSeeTableRecords([$withAlice])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('dave@example.com')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$withAlice]);
    }

    public function test_list_can_filter_by_status(): void
    {
        $pending = Friendship::factory()->create();
        $accepted = Friendship::factory()->accepted()->create();

        Livewire::test(ListFriendships::class)
            ->filterTable('status', Friendship::STATUS_ACCEPTED)
            ->assertCanSeeTableRecords([$accepted])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_view_page_renders(): void
    {
        $friendship = Friendship::factory()->accepted()->create();

        Livewire::test(ViewFriendship::class, ['record' => $friendship->getKey()])->assertOk();
    }

    public function test_the_resource_is_read_only(): void
    {
        $friendship = Friendship::factory()->create();

        $this->assertSame(['index', 'view'], array_keys(FriendshipResource::getPages()));
        $this->assertFalse(FriendshipResource::canCreate());
        $this->assertFalse(FriendshipResource::canEdit($friendship));
        $this->assertFalse(FriendshipResource::canDelete($friendship));
        $this->assertFalse(FriendshipResource::canDeleteAny());
    }

    public function test_only_allow_listed_admins_can_open_it_over_http(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']))
            ->get('/admin/friendships')
            ->assertOk();

        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
            ->get('/admin/friendships')
            ->assertForbidden();

        $this->actingAs(Member::factory()->create(), 'member')
            ->get('/admin/friendships')
            ->assertRedirect();
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Messages/FriendshipResourceTest.php`
Expected: FAIL，欄位或頁面不符。

- [ ] **Step 4: 實作資源**

`FriendshipResource.php` 完整取代：

```php
<?php

namespace App\Filament\Resources\Friendships;

use App\Filament\Resources\Friendships\Pages\ListFriendships;
use App\Filament\Resources\Friendships\Pages\ViewFriendship;
use App\Filament\Resources\Friendships\Schemas\FriendshipInfolist;
use App\Filament\Resources\Friendships\Tables\FriendshipsTable;
use App\Models\Friendship;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FriendshipResource extends Resource
{
    protected static ?string $model = Friendship::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = '會員';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = '好友關係';

    protected static ?string $pluralModelLabel = '好友關係';

    public static function infolist(Schema $schema): Schema
    {
        return FriendshipInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FriendshipsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFriendships::route('/'),
            'view' => ViewFriendship::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
```

`Tables/FriendshipsTable.php`：

```php
<?php

namespace App\Filament\Resources\Friendships\Tables;

use App\Models\Friendship;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FriendshipsTable
{
    public static function configure(Table $table): Table
    {
        $memberSearch = function (string $relation): \Closure {
            return fn (Builder $query, string $search): Builder => $query->whereHas(
                $relation,
                fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            );
        };

        return $table
            ->columns([
                TextColumn::make('low.name')
                    ->label('會員甲')
                    ->searchable(query: $memberSearch('low')),
                TextColumn::make('high.name')
                    ->label('會員乙')
                    ->searchable(query: $memberSearch('high')),
                TextColumn::make('requester.name')
                    ->label('邀請者'),
                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Friendship::STATUS_ACCEPTED ? '已成為好友' : '等待回覆')
                    ->color(fn (string $state): string => $state === Friendship::STATUS_ACCEPTED ? 'success' : 'warning'),
                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('responded_at')
                    ->label('回應時間')
                    ->dateTime()
                    ->placeholder('尚未回應'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('狀態')
                    ->options([
                        Friendship::STATUS_PENDING => '等待回覆',
                        Friendship::STATUS_ACCEPTED => '已成為好友',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
```

`Schemas/FriendshipInfolist.php`：

```php
<?php

namespace App\Filament\Resources\Friendships\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FriendshipInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('low.name')->label('會員甲'),
            TextEntry::make('low.email')->label('會員甲 Email'),
            TextEntry::make('high.name')->label('會員乙'),
            TextEntry::make('high.email')->label('會員乙 Email'),
            TextEntry::make('requester.name')->label('邀請者'),
            TextEntry::make('status')->label('狀態'),
            TextEntry::make('created_at')->label('建立時間')->dateTime(),
            TextEntry::make('responded_at')->label('回應時間')->dateTime()->placeholder('尚未回應'),
        ]);
    }
}
```

`Pages/ListFriendships.php` 與 `Pages/ViewFriendship.php` 移除所有 `getHeaderActions()` 內容，`ViewFriendship` 不含編輯動作，兩者各保留 `protected static string $resource = FriendshipResource::class;`。

並在 `MemberResource` 加入 `use UnitEnum;` 與：

```php
protected static string|UnitEnum|null $navigationGroup = '會員';

protected static ?int $navigationSort = 10;
```

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Messages/FriendshipResourceTest.php tests/Feature/Member`
Expected: PASS。搜尋測試若因全域搜尋語法不同而失敗，改用 Filament 文件的 `searchable(query: ...)` 範例，不要放寬測試。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament tests/Feature/Messages/FriendshipResourceTest.php
git commit -m "Add a read-only friendships resource to the admin panel"
```

---

### Task 6: 後台唯讀的訊息資源

**Files:**
- Create: `app/Filament/Resources/Messages/MessageResource.php`、`Pages/ListMessages.php`、`Pages/ViewMessage.php`、`Tables/MessagesTable.php`、`Schemas/MessageInfolist.php`
- Test: `tests/Feature/Messages/MessageResourceTest.php`

**Interfaces:**
- Consumes: Task 1 的 `Message`。
- Produces: 後台「訊息」唯讀資源，檢視頁顯示同一配對前後各 5 則訊息。

- [ ] **Step 1: 產生資源**

```bash
php artisan make:filament-resource Message --view --no-interaction
rm -f app/Filament/Resources/Messages/Pages/CreateMessage.php app/Filament/Resources/Messages/Pages/EditMessage.php
find app/Filament/Resources/Messages -type f
```

- [ ] **Step 2: 寫失敗的測試**

```bash
php artisan make:test Messages/MessageResourceTest --phpunit --no-interaction
```

```php
<?php

namespace Tests\Feature\Messages;

use App\Filament\Resources\Messages\MessageResource;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Models\Member;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MessageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
    }

    public function test_list_shows_messages_with_their_content(): void
    {
        $messages = Message::factory()->count(3)->create();

        Livewire::test(ListMessages::class)
            ->assertOk()
            ->assertCanSeeTableRecords($messages);
    }

    public function test_list_can_search_by_body_and_member(): void
    {
        $alice = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com']);
        $bob = Member::factory()->create(['name' => 'Bob Lin', 'email' => 'bob@example.com']);
        $about = Message::factory()->from($alice, $bob)->create(['body' => '關於咖啡的對話']);
        $other = Message::factory()->create(['body' => '完全無關']);

        Livewire::test(ListMessages::class)
            ->searchTable('咖啡')
            ->assertCanSeeTableRecords([$about])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('bob@example.com')
            ->assertCanSeeTableRecords([$about])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_list_can_filter_by_read_state(): void
    {
        $unread = Message::factory()->create();
        $read = Message::factory()->read()->create();

        Livewire::test(ListMessages::class)
            ->filterTable('read_at', true)
            ->assertCanSeeTableRecords([$read])
            ->assertCanNotSeeTableRecords([$unread])
            ->filterTable('read_at', false)
            ->assertCanSeeTableRecords([$unread])
            ->assertCanNotSeeTableRecords([$read]);
    }

    public function test_view_page_shows_the_full_body_and_surrounding_messages(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Message::factory()->from($a, $b)->create(['body' => '前一則']);
        $target = Message::factory()->from($b, $a)->create(['body' => str_repeat('長', 300)]);
        Message::factory()->from($a, $b)->create(['body' => '後一則']);
        Message::factory()->create(['body' => '別人的對話']);

        Livewire::test(ViewMessage::class, ['record' => $target->getKey()])
            ->assertOk()
            ->assertSee(str_repeat('長', 300))
            ->assertSee('前一則')
            ->assertSee('後一則')
            ->assertDontSee('別人的對話');
    }

    public function test_the_resource_is_read_only(): void
    {
        $message = Message::factory()->create();

        $this->assertSame(['index', 'view'], array_keys(MessageResource::getPages()));
        $this->assertFalse(MessageResource::canCreate());
        $this->assertFalse(MessageResource::canEdit($message));
        $this->assertFalse(MessageResource::canDelete($message));
        $this->assertFalse(MessageResource::canDeleteAny());
    }

    public function test_only_allow_listed_admins_can_open_it_over_http(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']))
            ->get('/admin/messages')
            ->assertOk();

        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
            ->get('/admin/messages')
            ->assertForbidden();

        $this->actingAs(Member::factory()->create(), 'member')
            ->get('/admin/messages')
            ->assertRedirect();
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Messages/MessageResourceTest.php`
Expected: FAIL。

- [ ] **Step 4: 實作資源**

`MessageResource.php` 完整取代：

```php
<?php

namespace App\Filament\Resources\Messages;

use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Filament\Resources\Messages\Schemas\MessageInfolist;
use App\Filament\Resources\Messages\Tables\MessagesTable;
use App\Models\Message;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = '會員';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = '訊息';

    protected static ?string $pluralModelLabel = '訊息';

    public static function infolist(Schema $schema): Schema
    {
        return MessageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MessagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessages::route('/'),
            'view' => ViewMessage::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
```

`Tables/MessagesTable.php`：

```php
<?php

namespace App\Filament\Resources\Messages\Tables;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        $memberSearch = function (string $relation): \Closure {
            return fn (Builder $query, string $search): Builder => $query->whereHas(
                $relation,
                fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            );
        };

        return $table
            ->columns([
                TextColumn::make('sender.name')
                    ->label('寄件者')
                    ->searchable(query: $memberSearch('sender')),
                TextColumn::make('recipient.name')
                    ->label('收件者')
                    ->searchable(query: $memberSearch('recipient')),
                TextColumn::make('body')
                    ->label('內容')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('傳送時間')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('read_at')
                    ->label('已讀時間')
                    ->dateTime()
                    ->placeholder('未讀'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                TernaryFilter::make('read_at')
                    ->label('已讀狀態')
                    ->nullable()
                    ->trueLabel('已讀')
                    ->falseLabel('未讀'),
                Filter::make('created_at')
                    ->label('傳送日期')
                    ->schema([
                        DatePicker::make('from')->label('從'),
                        DatePicker::make('until')->label('到'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
```

`Schemas/MessageInfolist.php`：

```php
<?php

namespace App\Filament\Resources\Messages\Schemas;

use App\Models\Message;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class MessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('sender.name')->label('寄件者'),
            TextEntry::make('sender.email')->label('寄件者 Email'),
            TextEntry::make('recipient.name')->label('收件者'),
            TextEntry::make('recipient.email')->label('收件者 Email'),
            TextEntry::make('created_at')->label('傳送時間')->dateTime(),
            TextEntry::make('read_at')->label('已讀時間')->dateTime()->placeholder('未讀'),
            TextEntry::make('body')->label('內容')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
            RepeatableEntry::make('context')
                ->label('同一配對的前後訊息')
                ->columnSpanFull()
                ->state(fn (Message $record): array => self::context($record))
                ->schema([
                    TextEntry::make('sender')->label('寄件者'),
                    TextEntry::make('created_at')->label('時間'),
                    TextEntry::make('body')->label('內容')->extraAttributes(['class' => 'whitespace-pre-line']),
                ]),
        ]);
    }

    /**
     * @return array<int, array{sender: string, created_at: string, body: string}>
     */
    private static function context(Message $record): array
    {
        $pair = fn ($query) => $query->between($record->sender, $record->recipient);

        $before = Message::query()->tap($pair)->where('id', '<', $record->getKey())->orderByDesc('id')->limit(5)->get()->reverse();
        $after = Message::query()->tap($pair)->where('id', '>', $record->getKey())->orderBy('id')->limit(5)->get();

        return $before->concat($after)
            ->map(fn (Message $message): array => [
                'sender' => $message->sender->name,
                'created_at' => $message->created_at->format('Y-m-d H:i'),
                'body' => $message->body,
            ])
            ->values()
            ->all();
    }
}
```

`Pages/ListMessages.php` 與 `Pages/ViewMessage.php` 移除 `getHeaderActions()` 內容，各保留 `protected static string $resource = MessageResource::class;`。

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Messages/MessageResourceTest.php`
Expected: PASS，6 個測試。若 `RepeatableEntry::state()` 在目前 Filament 版本的簽名不同，改用 `->getStateUsing(...)`，並以 `Ruling:` 記錄，不要刪除前後訊息的斷言。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament tests/Feature/Messages/MessageResourceTest.php
git commit -m "Add a read-only messages resource with conversation context"
```

---

### Task 7: 整體驗證

**Files:** 無新增。

- [ ] **Step 1: 執行會員、好友與短訊測試**

Run: `php artisan test --compact tests/Feature/Member tests/Feature/Friends tests/Feature/Messages`
Expected: 全部 PASS。

- [ ] **Step 2: 執行整個套件**

Run: `php artisan test --compact`
Expected: 全部 PASS。

- [ ] **Step 3: 手動確認**

```bash
php artisan migrate --no-interaction
npm run build
```

用兩個瀏覽器分頁登入兩位已互為好友的會員，確認：

- 好友清單出現「傳訊息」，傳送後對方的未讀數增加，對方開啟後我這邊顯示「已讀」。
- 訊息中的 HTML 以純文字顯示，換行保留。
- 移除好友後對話消失，封鎖後對方打開舊連結得到 404。
- 提醒信出現在 `storage/logs/laravel.log`，不含訊息內容。
- 後台出現「好友關係」與「訊息」，可搜尋與檢視，沒有新增、編輯、刪除。

- [ ] **Step 4: 回報**

回報階段二完成，並提醒部署前需要執行 migration，以及在使用條款或隱私說明載明管理者可以檢視訊息。

---

## 不在本計畫內

- 即時推播、附件、群組、訊息編輯與撤回、檢舉按鈕。
- 會員的語系偏好，提醒信目前固定使用繁體中文。
- 後台對訊息的任何寫入動作。
- 使用條款與隱私說明文件本身。
