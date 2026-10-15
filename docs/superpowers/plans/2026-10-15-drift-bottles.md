# 會員漂流瓶 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans, 此專案一律用 Native 執行。Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 會員可以丟出匿名瓶子給陌生會員，也能從海裡撿，收件人匿名回覆一次，先送後審並可檢舉，管理員在後台處理。

**Architecture:** `DriftBottle` 模型承載全部狀態與規則，狀態由欄位推導。指派、撿取以條件式 `update` 搶佔，避免重複取得。釋放由每小時排程完成，檢舉記錄在 `drift_bottle_reports`，下架以 `removed_at` 與 `reply_removed_at` 隱藏內容。

**Tech Stack:** Laravel 13、PHP 8.5、Filament 4、PHPUnit 12，沿用 UUIDv7 與既有的會員、好友、封鎖模型。

**Spec:** `docs/superpowers/specs/2026-10-15-drift-bottles-design.md`

## Global Constraints

- 主鍵 `HasUuids`，外鍵 `foreignUuid`，只新增 migration，不改既有 migration。
- 模型用 `#[Fillable([...])]` Attribute，不用 `protected $fillable`，見 `.ai/rules/models.md`。
- 路由名稱 `member.bottles...` 與 `.en`，路徑在 `/member/bottles` 下，放進 `['auth:member', 'member.verified']` 群組。
- 所有前台頁面 `noindex`，繁體中文與英文各一組文案，文案放 `lang/{zh_TW,en}/bottles.php`。
- 任何前台頁面、通知與網址都不得出現對方的姓名、Email、組織與會員編號。
- 後台文字使用繁體中文，導覽群組「會員」，全部唯讀，不提供編輯內容。
- 提醒信不含內文，語系固定 `zh_TW`，寄信失敗只記錄，用 `MemberController::sendQuietly`。
- 每個 PHP 修改後執行 `vendor/bin/pint --dirty --format agent`，測試用 factory。
- 測試環境預設語系是 `en`，中文頁面測試使用 `route('member.bottles')` 這類中文路由，並以 `Auth::forgetGuards()` 切換會員。
- 不新增依賴，不新增 `app/` 底下的基底資料夾。

## 決策紀錄

- 每日撿取次數用 `RateLimiter` 的 24 小時計數，不新增資料表欄位。
- 規格 4.9 說收件人被刪除時「瓶子回到海裡」，實作上只有尚未開啟的瓶子回到海裡，已開啟的瓶子保留給寄件者看，避免別人撿到已被看過的內容。
- 並發持有上限是盡力而為，兩個請求同時指派可能讓某位收件人短暫超過 5 個，不影響正確性。
- 排除名單用 `released_recipient_ids` JSON，查詢用 `whereJsonDoesntContain`，sqlite 與 MySQL 都支援。

## Review Focus

- 兩位會員同時撿同一個瓶子，只有一位拿到。
- 撿取與指派都不會把瓶子給寄件者本人、好友、待確認好友、封鎖對象、停用或未驗證的會員。
- 被檢舉下架的瓶子不會被釋放、重新指派或撿取。
- 收件人與寄件者的頁面、通知與網址不洩漏對方身分。
- 回覆只能一次，下架後不能回覆，非參與者一律 404。
- 寄件者被停用後，他在海裡的瓶子不會再被撿到。

---

## File Structure

| 檔案 | 責任 |
| :---- | :---- |
| `database/migrations/*_create_drift_bottle_tables.php` | 兩張資料表 |
| `app/Models/DriftBottle.php` | 狀態推導、丟出、指派、撿取、釋放、開啟、回覆、檢舉 |
| `app/Models/DriftBottleReport.php` | 檢舉紀錄 |
| `app/Console/Commands/ReleaseDriftBottles.php` | 每小時釋放 |
| `app/Http/Controllers/Member/BottleController.php` | 前台所有動作 |
| `app/Notifications/DriftBottleReceivedNotification.php`、`DriftBottleRepliedNotification.php` | 提醒信 |
| `resources/views/member/bottles.blade.php`、`bottle-create.blade.php`、`bottle-show.blade.php` | 前台頁面 |
| `lang/zh_TW/bottles.php`、`lang/en/bottles.php` | 文案 |
| `app/Filament/Resources/DriftBottles/*` | 後台資源與檢舉關聯管理器 |
| `tests/Feature/Bottles/*` | 測試 |

---

### Task 1: 資料層與模型規則

**Files:**
- Create: `database/migrations/2026_10_15_000000_create_drift_bottle_tables.php`
- Create: `app/Models/DriftBottle.php`、`app/Models/DriftBottleReport.php`
- Create: `database/factories/DriftBottleFactory.php`、`database/factories/DriftBottleReportFactory.php`
- Test: `tests/Feature/Bottles/DriftBottleModelTest.php`

**Interfaces:**
- Produces 常數：`DriftBottle::MAX_LENGTH = 300`、`HOLD_LIMIT = 5`、`DAILY_THROW_LIMIT = 3`、`DAILY_PICK_LIMIT = 10`、`RELEASE_AFTER_HOURS = 24`
- Produces：`status(): string`，值為 `in_sea`、`delivered`、`opened`、`replied`、`removed`
- Produces 靜態：`throwFrom(Member $sender, string $body): self`、`pick(Member $member): ?self`、`heldCount(Member $member): int`、`throwsToday(Member $member): int`、`containsContactInfo(string $text): bool`
- Produces 實例：`open(Member $by): void`、`replyFrom(Member $by, string $body): bool`、`reportBy(Member $by, string $target, ?string $reason = null): bool`、`release(): void`
- Produces scope：`inSea`、`receivedBy(Member)`、`sentBy(Member)`
- Produces 後台用：`removeBottle(): void`、`restoreBottle(): void`、`removeReply(): void`、`restoreReply(): void`

- [ ] **Step 1: 建立 migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drift_bottles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')->constrained('members')->cascadeOnDelete();
            $table->foreignUuid('recipient_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('body', 300);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->string('reply_body', 300)->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamp('reply_removed_at')->nullable();
            $table->json('released_recipient_ids')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'opened_at']);
            $table->index(['sender_id', 'created_at']);
        });

        Schema::create('drift_bottle_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('bottle_id')->constrained('drift_bottles')->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->constrained('members')->cascadeOnDelete();
            $table->string('target', 10);
            $table->string('reason', 200)->nullable();
            $table->string('outcome', 20)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['bottle_id', 'reporter_id', 'target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drift_bottle_reports');
        Schema::dropIfExists('drift_bottles');
    }
};
```

- [ ] **Step 2: 寫失敗的測試**

`tests/Feature/Bottles/DriftBottleModelTest.php`，類別 `Tests\Feature\Bottles\DriftBottleModelTest`，`RefreshDatabase`。每個測試先以 `Member::factory()` 建立資料。

```php
public function test_status_follows_the_columns(): void
{
    $bottle = DriftBottle::factory()->create();
    $this->assertSame('in_sea', $bottle->status());

    $bottle->forceFill(['recipient_id' => Member::factory()->create()->id])->save();
    $this->assertSame('delivered', $bottle->status());

    $bottle->forceFill(['opened_at' => now()])->save();
    $this->assertSame('opened', $bottle->status());

    $bottle->forceFill(['replied_at' => now(), 'reply_body' => 'hi'])->save();
    $this->assertSame('replied', $bottle->status());

    $bottle->forceFill(['removed_at' => now()])->save();
    $this->assertSame('removed', $bottle->status());
}

public function test_throwing_assigns_a_stranger(): void
{
    $sender = Member::factory()->create();
    $stranger = Member::factory()->create();

    $bottle = DriftBottle::throwFrom($sender, '哈囉');

    $this->assertTrue($bottle->recipient->is($stranger));
    $this->assertNotNull($bottle->assigned_at);
}

public function test_the_bottle_stays_in_the_sea_without_an_eligible_stranger(): void
{
    $sender = Member::factory()->create();

    $bottle = DriftBottle::throwFrom($sender, '哈囉');

    $this->assertSame('in_sea', $bottle->status());
}

public function test_ineligible_members_never_receive_it(): void
{
    $sender = Member::factory()->create();
    $friend = Member::factory()->create();
    $invited = Member::factory()->create();
    $blocked = Member::factory()->create();
    $blocker = Member::factory()->create();
    Member::factory()->inactive()->create();
    Member::factory()->unverified()->create();
    Friendship::factory()->between($sender, $friend)->accepted()->create();
    Friendship::factory()->between($sender, $invited)->create();
    MemberBlock::factory()->create(['blocker_id' => $sender->id, 'blocked_id' => $blocked->id]);
    MemberBlock::factory()->create(['blocker_id' => $blocker->id, 'blocked_id' => $sender->id]);

    $bottle = DriftBottle::throwFrom($sender, '哈囉');

    $this->assertSame('in_sea', $bottle->status());
}

public function test_a_member_holding_five_unopened_bottles_is_skipped(): void
{
    $sender = Member::factory()->create();
    $holder = Member::factory()->create();
    DriftBottle::factory()->count(5)->create(['recipient_id' => $holder->id, 'assigned_at' => now()]);

    $bottle = DriftBottle::throwFrom($sender, '哈囉');

    $this->assertSame('in_sea', $bottle->status());
}

public function test_pick_takes_a_bottle_from_the_sea_and_never_your_own(): void
{
    $sender = Member::factory()->create();
    $picker = Member::factory()->create();
    $own = DriftBottle::factory()->create(['sender_id' => $picker->id]);
    $other = DriftBottle::factory()->create(['sender_id' => $sender->id]);

    $picked = DriftBottle::pick($picker);

    $this->assertTrue($picked->is($other));
    $this->assertTrue($picked->recipient->is($picker));
    $this->assertSame('in_sea', $own->fresh()->status());
}

public function test_pick_skips_friends_blocked_inactive_senders_and_released_ones(): void
{
    $picker = Member::factory()->create();
    $friend = Member::factory()->create();
    $blocked = Member::factory()->create();
    $inactive = Member::factory()->inactive()->create();
    $other = Member::factory()->create();
    Friendship::factory()->between($picker, $friend)->accepted()->create();
    MemberBlock::factory()->create(['blocker_id' => $picker->id, 'blocked_id' => $blocked->id]);
    DriftBottle::factory()->create(['sender_id' => $friend->id]);
    DriftBottle::factory()->create(['sender_id' => $blocked->id]);
    DriftBottle::factory()->create(['sender_id' => $inactive->id]);
    DriftBottle::factory()->create(['sender_id' => $other->id, 'released_recipient_ids' => [$picker->id]]);
    DriftBottle::factory()->create(['sender_id' => $other->id, 'removed_at' => now()]);

    $this->assertNull(DriftBottle::pick($picker));
}

public function test_only_one_member_wins_a_contested_bottle(): void
{
    $bottle = DriftBottle::factory()->create();
    $a = Member::factory()->create();
    $b = Member::factory()->create();

    $first = DriftBottle::pick($a);
    $second = DriftBottle::pick($b);

    $this->assertTrue($first->is($bottle));
    $this->assertNull($second);
    $this->assertTrue($bottle->fresh()->recipient->is($a));
}

public function test_release_returns_unopened_bottles_and_remembers_the_recipient(): void
{
    $recipient = Member::factory()->create();
    $bottle = DriftBottle::factory()->create([
        'recipient_id' => $recipient->id,
        'assigned_at' => now()->subHours(25),
    ]);

    $this->artisan('drift-bottles:release')->assertSuccessful();

    $fresh = $bottle->fresh();
    $this->assertNull($fresh->recipient_id);
    $this->assertNull($fresh->assigned_at);
    $this->assertSame([$recipient->id], $fresh->released_recipient_ids);
}

public function test_release_leaves_recent_opened_replied_and_removed_bottles(): void
{
    $recipient = Member::factory()->create();
    $old = now()->subHours(25);
    $recent = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'assigned_at' => now()->subHours(2)]);
    $opened = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'assigned_at' => $old, 'opened_at' => now()]);
    $removed = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'assigned_at' => $old, 'removed_at' => now()]);

    $this->artisan('drift-bottles:release')->assertSuccessful();

    foreach ([$recent, $opened, $removed] as $bottle) {
        $this->assertNotNull($bottle->fresh()->recipient_id);
    }
}

public function test_only_the_recipient_opens_and_replies_once(): void
{
    $recipient = Member::factory()->create();
    $stranger = Member::factory()->create();
    $bottle = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'assigned_at' => now()]);

    $this->assertFalse($bottle->replyFrom($recipient, '早'));

    $bottle->open($stranger);
    $this->assertNull($bottle->fresh()->opened_at);

    $bottle->open($recipient);
    $this->assertNotNull($bottle->fresh()->opened_at);

    $this->assertFalse($bottle->fresh()->replyFrom($stranger, '偷回'));
    $this->assertTrue($bottle->fresh()->replyFrom($recipient, '收到'));
    $this->assertFalse($bottle->fresh()->replyFrom($recipient, '再回'));
    $this->assertSame('收到', $bottle->fresh()->reply_body);
}

public function test_a_removed_bottle_cannot_be_replied_to(): void
{
    $recipient = Member::factory()->create();
    $bottle = DriftBottle::factory()->create([
        'recipient_id' => $recipient->id, 'opened_at' => now(), 'removed_at' => now(),
    ]);

    $this->assertFalse($bottle->replyFrom($recipient, '收到'));
}

public function test_reporting_hides_the_content_and_is_recorded_once(): void
{
    $recipient = Member::factory()->create();
    $bottle = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'opened_at' => now()]);

    $this->assertTrue($bottle->reportBy($recipient, 'bottle', '不當內容'));
    $this->assertFalse($bottle->fresh()->reportBy($recipient, 'bottle'));

    $this->assertNotNull($bottle->fresh()->removed_at);
    $this->assertSame(1, DriftBottleReport::query()->count());
}

public function test_only_the_sender_can_report_a_reply(): void
{
    $recipient = Member::factory()->create();
    $bottle = DriftBottle::factory()->create([
        'recipient_id' => $recipient->id, 'opened_at' => now(),
        'replied_at' => now(), 'reply_body' => '回覆',
    ]);

    $this->assertFalse($bottle->reportBy($recipient, 'reply'));
    $this->assertTrue($bottle->reportBy($bottle->sender, 'reply'));
    $this->assertNotNull($bottle->fresh()->reply_removed_at);
    $this->assertNull($bottle->fresh()->removed_at);
}

public function test_contact_information_is_detected(): void
{
    foreach (['https://example.com', 'www.example.com', 'a@b.co', 'HTTP://X.TW'] as $text) {
        $this->assertTrue(DriftBottle::containsContactInfo("看 $text 吧"), $text);
    }

    $this->assertFalse(DriftBottle::containsContactInfo('今天天氣很好'));
}

public function test_counters(): void
{
    $sender = Member::factory()->create();
    $holder = Member::factory()->create();
    DriftBottle::factory()->count(2)->create(['sender_id' => $sender->id]);
    DriftBottle::factory()->create(['sender_id' => $sender->id, 'created_at' => now()->subDays(2)]);
    DriftBottle::factory()->create(['recipient_id' => $holder->id]);
    DriftBottle::factory()->create(['recipient_id' => $holder->id, 'opened_at' => now()]);

    $this->assertSame(2, DriftBottle::throwsToday($sender));
    $this->assertSame(1, DriftBottle::heldCount($holder));
}

public function test_deleting_a_recipient_returns_unopened_bottles_to_the_sea(): void
{
    $recipient = Member::factory()->create();
    $unopened = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'assigned_at' => now()]);
    $opened = DriftBottle::factory()->create(['recipient_id' => $recipient->id, 'opened_at' => now()]);

    $recipient->delete();

    $this->assertSame('in_sea', $unopened->fresh()->status());
    $this->assertSame('opened', $opened->fresh()->status());
}

public function test_deleting_a_sender_deletes_the_bottle(): void
{
    $bottle = DriftBottle::factory()->create();

    $bottle->sender->delete();

    $this->assertNull(DriftBottle::query()->find($bottle->id));
}
```

`test_deleting_a_recipient...` 的 `opened` 瓶子 `recipient_id` 變 null 但 `opened_at` 有值，`status()` 的判斷順序必須是：`removed_at` → `replied_at` → `opened_at` → `recipient_id` → `in_sea`，並且 `inSea` scope 與 `pick` 要求 `opened_at` 為空。

- [ ] **Step 3: 驗證失敗**

Run: `php artisan test --compact tests/Feature/Bottles/DriftBottleModelTest.php`
Expected: FAIL，類別不存在。

- [ ] **Step 4: 實作模型、factory 與指令**

`app/Models/DriftBottle.php`：

```php
<?php

namespace App\Models;

use Database\Factories\DriftBottleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\RateLimiter;

#[Fillable(['sender_id', 'recipient_id', 'body'])]
class DriftBottle extends Model
{
    /** @use HasFactory<DriftBottleFactory> */
    use HasFactory, HasUuids;

    public const MAX_LENGTH = 300;

    public const HOLD_LIMIT = 5;

    public const DAILY_THROW_LIMIT = 3;

    public const DAILY_PICK_LIMIT = 10;

    public const RELEASE_AFTER_HOURS = 24;

    public const STATUS_IN_SEA = 'in_sea';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_OPENED = 'opened';

    public const STATUS_REPLIED = 'replied';

    public const STATUS_REMOVED = 'removed';

    public const TARGET_BOTTLE = 'bottle';

    public const TARGET_REPLY = 'reply';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'opened_at' => 'datetime',
            'replied_at' => 'datetime',
            'removed_at' => 'datetime',
            'reply_removed_at' => 'datetime',
            'released_recipient_ids' => 'array',
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

    public function reports(): HasMany
    {
        return $this->hasMany(DriftBottleReport::class, 'bottle_id');
    }

    public function status(): string
    {
        return match (true) {
            $this->removed_at !== null => self::STATUS_REMOVED,
            $this->replied_at !== null => self::STATUS_REPLIED,
            $this->opened_at !== null => self::STATUS_OPENED,
            $this->recipient_id !== null => self::STATUS_DELIVERED,
            default => self::STATUS_IN_SEA,
        };
    }

    /**
     * @param  Builder<DriftBottle>  $query
     * @return Builder<DriftBottle>
     */
    public function scopeInSea(Builder $query): Builder
    {
        return $query->whereNull('recipient_id')
            ->whereNull('opened_at')
            ->whereNull('removed_at');
    }

    /**
     * @param  Builder<DriftBottle>  $query
     * @return Builder<DriftBottle>
     */
    public function scopeReceivedBy(Builder $query, Member $member): Builder
    {
        return $query->where('recipient_id', $member->getKey())->whereNull('removed_at');
    }

    /**
     * @param  Builder<DriftBottle>  $query
     * @return Builder<DriftBottle>
     */
    public function scopeSentBy(Builder $query, Member $member): Builder
    {
        return $query->where('sender_id', $member->getKey());
    }

    public static function containsContactInfo(string $text): bool
    {
        return (bool) preg_match('~https?://|www\.|[^\s@]+@[^\s@]+\.[^\s@]+~iu', $text);
    }

    public static function throwsToday(Member $member): int
    {
        return static::query()
            ->where('sender_id', $member->getKey())
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    public static function heldCount(Member $member): int
    {
        return static::query()
            ->where('recipient_id', $member->getKey())
            ->whereNull('opened_at')
            ->whereNull('removed_at')
            ->count();
    }

    public static function pickKey(Member $member): string
    {
        return 'bottle-pick:'.$member->getKey();
    }

    public static function picksLeft(Member $member): int
    {
        return max(0, self::DAILY_PICK_LIMIT - RateLimiter::attempts(self::pickKey($member)));
    }

    public static function throwFrom(Member $sender, string $body): self
    {
        $bottle = static::query()->create([
            'sender_id' => $sender->getKey(),
            'body' => $body,
        ]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $recipient = $bottle->eligibleRecipients()->inRandomOrder()->first();

            if ($recipient === null) {
                break;
            }

            if ($bottle->claimFor($recipient)) {
                break;
            }
        }

        return $bottle->fresh();
    }

    /**
     * Take one bottle out of the sea for the member, or null when none is available.
     */
    public static function pick(Member $member): ?self
    {
        $excluded = self::strangerExclusions($member);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $bottle = static::query()
                ->inSea()
                ->where('sender_id', '!=', $member->getKey())
                ->whereNotIn('sender_id', $excluded)
                ->whereHas('sender', fn (Builder $query) => $query->where('is_active', true))
                ->whereJsonDoesntContain('released_recipient_ids', $member->getKey())
                ->inRandomOrder()
                ->first();

            if ($bottle === null) {
                return null;
            }

            if ($bottle->claimFor($member)) {
                return $bottle->fresh();
            }
        }

        return null;
    }

    /**
     * @return Builder<Member>
     */
    public function eligibleRecipients(): Builder
    {
        $sender = $this->sender;
        $excluded = self::strangerExclusions($sender)->push($sender->getKey())
            ->merge($this->released_recipient_ids ?? []);

        $saturated = static::query()
            ->select('recipient_id')
            ->whereNotNull('recipient_id')
            ->whereNull('opened_at')
            ->whereNull('removed_at')
            ->groupBy('recipient_id')
            ->havingRaw('count(*) >= ?', [self::HOLD_LIMIT]);

        return Member::query()
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->whereNotIn('id', $excluded)
            ->whereNotIn('id', $saturated);
    }

    /**
     * Friends, pending invitations and blocks in either direction.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private static function strangerExclusions(Member $member): \Illuminate\Support\Collection
    {
        $related = Friendship::query()
            ->involving($member)
            ->get(['member_low_id', 'member_high_id'])
            ->flatMap(fn (Friendship $friendship) => [$friendship->member_low_id, $friendship->member_high_id]);

        return $related
            ->merge($member->blockedPairMemberIds())
            ->reject(fn (string $id) => $id === $member->getKey())
            ->unique()
            ->values();
    }

    private function claimFor(Member $recipient): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->whereNull('recipient_id')
            ->whereNull('opened_at')
            ->whereNull('removed_at')
            ->update(['recipient_id' => $recipient->getKey(), 'assigned_at' => now()]) === 1;
    }

    public function release(): void
    {
        $released = $this->released_recipient_ids ?? [];

        if ($this->recipient_id !== null) {
            $released[] = $this->recipient_id;
        }

        $this->forceFill([
            'recipient_id' => null,
            'assigned_at' => null,
            'released_recipient_ids' => array_values(array_unique($released)),
        ])->save();
    }

    public function open(Member $by): void
    {
        if ($this->recipient_id !== $by->getKey() || $this->removed_at !== null || $this->opened_at !== null) {
            return;
        }

        static::query()->whereKey($this->getKey())->whereNull('opened_at')->update(['opened_at' => now()]);
    }

    public function replyFrom(Member $by, string $body): bool
    {
        if ($this->recipient_id !== $by->getKey() || $this->opened_at === null || $this->removed_at !== null) {
            return false;
        }

        return static::query()
            ->whereKey($this->getKey())
            ->whereNull('replied_at')
            ->whereNull('removed_at')
            ->update(['reply_body' => $body, 'replied_at' => now()]) === 1;
    }

    public function reportBy(Member $by, string $target, ?string $reason = null): bool
    {
        $allowed = match ($target) {
            self::TARGET_BOTTLE => $this->recipient_id === $by->getKey() && $this->removed_at === null,
            self::TARGET_REPLY => $this->sender_id === $by->getKey()
                && $this->replied_at !== null
                && $this->reply_removed_at === null,
            default => false,
        };

        if (! $allowed) {
            return false;
        }

        $report = DriftBottleReport::query()->firstOrCreate(
            ['bottle_id' => $this->getKey(), 'reporter_id' => $by->getKey(), 'target' => $target],
            ['reason' => $reason !== null ? mb_substr($reason, 0, 200) : null],
        );

        if (! $report->wasRecentlyCreated) {
            return false;
        }

        $column = $target === self::TARGET_BOTTLE ? 'removed_at' : 'reply_removed_at';
        $this->forceFill([$column => now()])->save();

        return true;
    }

    public function removeBottle(): void
    {
        $this->forceFill(['removed_at' => now()])->save();
    }

    public function restoreBottle(): void
    {
        $this->forceFill(['removed_at' => null])->save();
    }

    public function removeReply(): void
    {
        $this->forceFill(['reply_removed_at' => now()])->save();
    }

    public function restoreReply(): void
    {
        $this->forceFill(['reply_removed_at' => null])->save();
    }
}
```

註：`replyFrom` 的條件式 `update` 回傳 1 才算成功，雙擊與併發只有一次會成功。

`app/Models/DriftBottleReport.php`：

```php
<?php

namespace App\Models;

use Database\Factories\DriftBottleReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bottle_id', 'reporter_id', 'target', 'reason'])]
class DriftBottleReport extends Model
{
    /** @use HasFactory<DriftBottleReportFactory> */
    use HasFactory, HasUuids;

    public const OUTCOME_VIOLATION = 'violation';

    public const OUTCOME_DISMISSED = 'dismissed';

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function bottle(): BelongsTo
    {
        return $this->belongsTo(DriftBottle::class, 'bottle_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reporter_id');
    }

    public function isPending(): bool
    {
        return $this->reviewed_at === null;
    }

    public function review(string $outcome, ?int $by): void
    {
        $this->forceFill(['outcome' => $outcome, 'reviewed_by' => $by, 'reviewed_at' => now()])->save();

        if ($outcome === self::OUTCOME_DISMISSED) {
            $this->target === DriftBottle::TARGET_BOTTLE
                ? $this->bottle->restoreBottle()
                : $this->bottle->restoreReply();
        }
    }
}
```

Factories：`DriftBottleFactory::definition()` 回傳 `['sender_id' => Member::factory(), 'recipient_id' => null, 'body' => fake()->sentence()]`；`DriftBottleReportFactory::definition()` 回傳 `['bottle_id' => DriftBottle::factory(), 'reporter_id' => Member::factory(), 'target' => 'bottle']`。

`app/Console/Commands/ReleaseDriftBottles.php`：

```php
<?php

namespace App\Console\Commands;

use App\Models\DriftBottle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('drift-bottles:release')]
#[Description('Return drift bottles that nobody opened within 24 hours to the sea')]
class ReleaseDriftBottles extends Command
{
    public function handle(): int
    {
        $released = 0;

        DriftBottle::query()
            ->whereNotNull('recipient_id')
            ->whereNull('opened_at')
            ->whereNull('removed_at')
            ->where('assigned_at', '<', now()->subHours(DriftBottle::RELEASE_AFTER_HOURS))
            ->each(function (DriftBottle $bottle) use (&$released): void {
                $bottle->release();
                $released++;
            });

        $this->info("Released {$released} drift bottles.");

        return self::SUCCESS;
    }
}
```

`routes/console.php` 加入 `Schedule::command('drift-bottles:release')->hourly();`。

- [ ] **Step 5: 驗證通過並 Commit**

Run: `php artisan test --compact tests/Feature/Bottles/DriftBottleModelTest.php`
Expected: PASS。

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Add drift bottle models, assignment, picking and release"
```

---

### Task 2: 前台流程

**Files:**
- Create: `app/Http/Controllers/Member/BottleController.php`
- Create: `resources/views/member/bottles.blade.php`、`bottle-create.blade.php`、`bottle-show.blade.php`
- Create: `lang/zh_TW/bottles.php`、`lang/en/bottles.php`
- Modify: `routes/web.php`、`app/Providers/AppServiceProvider.php`、`resources/views/member/center.blade.php`
- Test: `tests/Feature/Bottles/BottleFlowTest.php`

**Interfaces:**
- Consumes: Task 1 的全部模型方法
- Produces: 路由 `member.bottles`、`.create`、`.store`、`.pick`、`.show`、`.reply`、`.report` 與 `.en`；限流器 `member-bottle`

- [ ] **Step 1: 寫失敗的測試**

`tests/Feature/Bottles/BottleFlowTest.php`，`RefreshDatabase`，`setUp` 呼叫 `RateLimiter::clear` 並提供 `asMember()`（`Auth::forgetGuards()` 後 `actingAs($member, 'member')`）。

```php
public function test_throwing_a_bottle_requires_conduct_agreement(): void
{
    $me = Member::factory()->create();

    $this->asMember($me)->post(route('member.bottles.store'), ['body' => '哈囉'])
        ->assertSessionHasErrors('agree_conduct');

    $this->assertSame(0, DriftBottle::query()->count());
}

public function test_throwing_validates_length_and_contact_info(): void
{
    $me = Member::factory()->create();

    foreach ([str_repeat('字', 301), '   ', '看 https://example.com', '寫信 a@b.co'] as $body) {
        $this->asMember($me)->post(route('member.bottles.store'), ['body' => $body, 'agree_conduct' => '1'])
            ->assertSessionHasErrors('body');
    }

    $this->assertSame(0, DriftBottle::query()->count());
}

public function test_a_bottle_is_thrown_and_delivered(): void
{
    Notification::fake();
    $me = Member::factory()->create();
    $stranger = Member::factory()->create();

    $this->asMember($me)->post(route('member.bottles.store'), ['body' => ' 哈囉 ', 'agree_conduct' => '1'])
        ->assertRedirect(route('member.bottles'));

    $bottle = DriftBottle::query()->firstOrFail();
    $this->assertSame('哈囉', $bottle->body);
    $this->assertTrue($bottle->recipient->is($stranger));
    Notification::assertSentTo($stranger, DriftBottleReceivedNotification::class);
}

public function test_the_fourth_bottle_in_a_day_is_refused(): void
{
    $me = Member::factory()->create();
    Member::factory()->create();
    DriftBottle::factory()->count(3)->create(['sender_id' => $me->id]);

    $this->asMember($me)->post(route('member.bottles.store'), ['body' => '哈囉', 'agree_conduct' => '1'])
        ->assertSessionHasErrors('body');

    $this->assertSame(3, DriftBottle::query()->count());
}

public function test_picking_a_bottle(): void
{
    $me = Member::factory()->create();
    $bottle = DriftBottle::factory()->create();

    $this->asMember($me)->post(route('member.bottles.pick'))
        ->assertRedirect(route('member.bottles.show', $bottle));

    $this->assertTrue($bottle->fresh()->recipient->is($me));
}

public function test_picking_from_an_empty_sea_does_not_use_a_pick(): void
{
    $me = Member::factory()->create();

    $this->asMember($me)->post(route('member.bottles.pick'))
        ->assertRedirect(route('member.bottles'))
        ->assertSessionHas('status');

    $this->assertSame(DriftBottle::DAILY_PICK_LIMIT, DriftBottle::picksLeft($me));
}

public function test_picking_is_blocked_when_holding_five_or_after_ten_picks(): void
{
    $me = Member::factory()->create();
    DriftBottle::factory()->count(5)->create(['recipient_id' => $me->id, 'assigned_at' => now()]);
    DriftBottle::factory()->create();

    $this->asMember($me)->post(route('member.bottles.pick'))->assertSessionHasErrors('pick');
    $this->assertNull(DriftBottle::query()->inSea()->first()->recipient_id);

    DriftBottle::query()->where('recipient_id', $me->id)->update(['opened_at' => now()]);
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit(DriftBottle::pickKey($me), 86400);
    }

    $this->asMember($me)->post(route('member.bottles.pick'))->assertSessionHasErrors('pick');
}

public function test_the_recipient_sees_the_bottle_anonymously_and_opens_it(): void
{
    $sender = Member::factory()->create(['name' => '寄件者小明', 'email' => 'sender@example.com', 'organization' => '神秘組織']);
    $me = Member::factory()->create();
    $bottle = DriftBottle::factory()->create(['sender_id' => $sender->id, 'recipient_id' => $me->id, 'body' => '嗨陌生人']);

    $this->asMember($me)->get(route('member.bottles.show', $bottle))
        ->assertOk()
        ->assertSee('嗨陌生人')
        ->assertDontSee('寄件者小明')
        ->assertDontSee('sender@example.com')
        ->assertDontSee('神秘組織')
        ->assertDontSee($sender->id);

    $this->assertNotNull($bottle->fresh()->opened_at);
}

public function test_the_sender_sees_the_reply_anonymously(): void
{
    $me = Member::factory()->create();
    $recipient = Member::factory()->create(['name' => '收件者小華', 'email' => 'rcpt@example.com']);
    $bottle = DriftBottle::factory()->create([
        'sender_id' => $me->id, 'recipient_id' => $recipient->id, 'opened_at' => now(),
        'replied_at' => now(), 'reply_body' => '收到你的瓶子了',
    ]);

    $this->asMember($me)->get(route('member.bottles.show', $bottle))
        ->assertOk()
        ->assertSee('收到你的瓶子了')
        ->assertDontSee('收件者小華')
        ->assertDontSee('rcpt@example.com')
        ->assertDontSee($recipient->id);
}

public function test_non_participants_get_404(): void
{
    $bottle = DriftBottle::factory()->create(['recipient_id' => Member::factory()->create()->id]);

    $this->asMember(Member::factory()->create())->get(route('member.bottles.show', $bottle))->assertNotFound();
    $this->asMember(Member::factory()->create())->post(route('member.bottles.reply', $bottle), ['body' => 'x'])->assertNotFound();
    $this->asMember(Member::factory()->create())->post(route('member.bottles.report', $bottle), ['target' => 'bottle'])->assertNotFound();
}

public function test_a_removed_bottle_is_hidden_from_the_recipient_but_flagged_for_the_sender(): void
{
    $sender = Member::factory()->create();
    $me = Member::factory()->create();
    $bottle = DriftBottle::factory()->create([
        'sender_id' => $sender->id, 'recipient_id' => $me->id, 'opened_at' => now(), 'removed_at' => now(), 'body' => '違規內容',
    ]);

    $this->asMember($me)->get(route('member.bottles.show', $bottle))->assertNotFound();
    $this->asMember($sender)->get(route('member.bottles.show', $bottle))
        ->assertOk()
        ->assertSee(__('bottles.show.removed'));
}

public function test_replying_notifies_the_sender_and_only_once(): void
{
    Notification::fake();
    $sender = Member::factory()->create();
    $me = Member::factory()->create();
    $bottle = DriftBottle::factory()->create(['sender_id' => $sender->id, 'recipient_id' => $me->id, 'opened_at' => now()]);

    $this->asMember($me)->post(route('member.bottles.reply', $bottle), ['body' => '收到'])
        ->assertRedirect(route('member.bottles.show', $bottle));
    $this->asMember($me)->post(route('member.bottles.reply', $bottle), ['body' => '再回'])
        ->assertSessionHasErrors('body');

    $this->assertSame('收到', $bottle->fresh()->reply_body);
    Notification::assertSentToTimes($sender, DriftBottleRepliedNotification::class, 1);
}

public function test_reporting_hides_the_bottle(): void
{
    $me = Member::factory()->create();
    $bottle = DriftBottle::factory()->create(['recipient_id' => $me->id, 'opened_at' => now()]);

    $this->asMember($me)->post(route('member.bottles.report', $bottle), ['target' => 'bottle', 'reason' => '騷擾'])
        ->assertRedirect(route('member.bottles'));

    $this->assertNotNull($bottle->fresh()->removed_at);
    $this->asMember($me)->get(route('member.bottles.show', $bottle))->assertNotFound();
}

public function test_guests_and_unverified_members_are_redirected(): void
{
    $this->get(route('member.bottles'))->assertRedirect(route('member.login'));
    $this->asMember(Member::factory()->unverified()->create())->get(route('member.bottles'))
        ->assertRedirect(route('member.verification.notice'));
}

public function test_english_pages_are_in_english(): void
{
    $me = Member::factory()->create();

    $this->asMember($me)->get(route('member.bottles.en'))
        ->assertOk()
        ->assertSee(trans('bottles.index.heading', [], 'en'), false)
        ->assertSee('noindex', false);
}

public function test_the_form_links_to_the_code_of_conduct(): void
{
    $this->asMember(Member::factory()->create())->get(route('member.bottles.create'))
        ->assertOk()
        ->assertSee(route('conduct'), false);
}
```

- [ ] **Step 2: 驗證失敗**

Run: `php artisan test --compact tests/Feature/Bottles/BottleFlowTest.php`
Expected: FAIL，路由不存在。

- [ ] **Step 3: 實作**

`AppServiceProvider::boot()` 新增：

```php
RateLimiter::for('member-bottle', fn (Request $request) => Limit::perMinute(10)
    ->by('member-bottle|'.($request->user('member')?->getKey() ?? $request->ip())));
```

`routes/web.php` 在已驗證群組內加入，順序必須是 `new` 在 `{bottle}` 之前，並 `use App\Http\Controllers\Member\BottleController;`：

```php
Route::get('/member/bottles', [BottleController::class, 'index'])->name('member.bottles'.$s);
Route::get('/member/bottles/new', [BottleController::class, 'create'])->name('member.bottles.create'.$s);
Route::post('/member/bottles', [BottleController::class, 'store'])->middleware('throttle:member-bottle')->name('member.bottles.store'.$s);
Route::post('/member/bottles/pick', [BottleController::class, 'pick'])->middleware('throttle:member-bottle')->name('member.bottles.pick'.$s);
Route::get('/member/bottles/{bottle}', [BottleController::class, 'show'])->name('member.bottles.show'.$s);
Route::post('/member/bottles/{bottle}/reply', [BottleController::class, 'reply'])->middleware('throttle:member-bottle')->name('member.bottles.reply'.$s);
Route::post('/member/bottles/{bottle}/report', [BottleController::class, 'report'])->middleware('throttle:member-bottle')->name('member.bottles.report'.$s);
```

`BottleController`：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\DriftBottle;
use App\Notifications\DriftBottleReceivedNotification;
use App\Notifications\DriftBottleRepliedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class BottleController extends MemberController
{
    public function index(): View
    {
        $me = $this->guard()->user();

        return view('member.bottles', [
            'received' => DriftBottle::query()->receivedBy($me)->latest('assigned_at')->get(),
            'sent' => DriftBottle::query()->sentBy($me)->latest()->get(),
            'holding' => DriftBottle::heldCount($me),
            'throwsLeft' => max(0, DriftBottle::DAILY_THROW_LIMIT - DriftBottle::throwsToday($me)),
            'picksLeft' => DriftBottle::picksLeft($me),
        ]);
    }

    public function create(): View
    {
        return view('member.bottle-create');
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $this->guard()->user();

        $this->trimBody($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.DriftBottle::MAX_LENGTH, function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && DriftBottle::containsContactInfo($value)) {
                    $fail(__('bottles.errors.contact'));
                }
            }],
            'agree_conduct' => ['accepted'],
        ]);

        if (DriftBottle::throwsToday($me) >= DriftBottle::DAILY_THROW_LIMIT) {
            throw ValidationException::withMessages(['body' => __('bottles.errors.daily_limit')]);
        }

        $bottle = DriftBottle::throwFrom($me, $data['body']);

        if ($bottle->recipient !== null) {
            $this->notifyReceived($bottle);
        }

        return redirect($this->localizedRoute('member.bottles'))->with('status', __('bottles.flash.thrown'));
    }

    public function pick(): RedirectResponse
    {
        $me = $this->guard()->user();

        if (DriftBottle::heldCount($me) >= DriftBottle::HOLD_LIMIT) {
            throw ValidationException::withMessages(['pick' => __('bottles.errors.holding')]);
        }

        if (RateLimiter::tooManyAttempts(DriftBottle::pickKey($me), DriftBottle::DAILY_PICK_LIMIT)) {
            throw ValidationException::withMessages(['pick' => __('bottles.errors.pick_limit')]);
        }

        $bottle = DriftBottle::pick($me);

        if ($bottle === null) {
            return redirect($this->localizedRoute('member.bottles'))->with('status', __('bottles.flash.empty_sea'));
        }

        RateLimiter::hit(DriftBottle::pickKey($me), 86400);

        return redirect($this->localizedRoute('member.bottles.show', ['bottle' => $bottle]));
    }

    public function show(DriftBottle $bottle): View
    {
        $me = $this->guard()->user();
        $isRecipient = $bottle->recipient_id === $me->getKey();
        $isSender = $bottle->sender_id === $me->getKey();

        abort_unless($isSender || $isRecipient, 404);
        abort_if($isRecipient && ! $isSender && $bottle->removed_at !== null, 404);

        if ($isRecipient) {
            $bottle->open($me);
            $bottle->refresh();
        }

        return view('member.bottle-show', [
            'bottle' => $bottle,
            'isRecipient' => $isRecipient,
        ]);
    }

    public function reply(Request $request, DriftBottle $bottle): RedirectResponse
    {
        $me = $this->guard()->user();

        abort_unless($bottle->recipient_id === $me->getKey() && $bottle->removed_at === null, 404);

        $this->trimBody($request);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.DriftBottle::MAX_LENGTH, function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && DriftBottle::containsContactInfo($value)) {
                    $fail(__('bottles.errors.contact'));
                }
            }],
        ]);

        if (! $bottle->replyFrom($me, $data['body'])) {
            throw ValidationException::withMessages(['body' => __('bottles.errors.cannot_reply')]);
        }

        $this->sendQuietly(function () use ($bottle): void {
            $bottle->sender->notify(
                (new DriftBottleRepliedNotification(route('member.bottles.show', $bottle)))->locale('zh_TW'),
            );
        });

        return redirect($this->localizedRoute('member.bottles.show', ['bottle' => $bottle]));
    }

    public function report(Request $request, DriftBottle $bottle): RedirectResponse
    {
        $me = $this->guard()->user();

        abort_unless($bottle->sender_id === $me->getKey() || $bottle->recipient_id === $me->getKey(), 404);

        $data = $request->validate([
            'target' => ['required', 'in:bottle,reply'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $bottle->reportBy($me, $data['target'], $data['reason'] ?? null);

        return redirect($this->localizedRoute('member.bottles'))->with('status', __('bottles.flash.reported'));
    }

    private function trimBody(Request $request): void
    {
        $body = $request->input('body');

        $request->merge(['body' => is_string($body) ? trim($body) : $body]);
    }

    private function notifyReceived(DriftBottle $bottle): void
    {
        $key = 'bottle-notify:'.$bottle->recipient_id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return;
        }

        $this->sendQuietly(function () use ($bottle, $key): void {
            $bottle->recipient->notify(
                (new DriftBottleReceivedNotification(route('member.bottles.show', $bottle)))->locale('zh_TW'),
            );

            RateLimiter::hit($key, 900);
        });
    }
}
```

視圖三個檔案沿用 `member.layout`，`@section('route', 'member.bottles')`，標題文案取自 `bottles.*`。

- `bottles.blade.php`：顯示 `session('status')`（layout 已處理）、`$errors->first('pick')`；區塊「收到的瓶子」列表每筆顯示狀態（未開啟、已開啟、已回覆）與連結；「我丟出的瓶子」列表每筆顯示狀態（漂流中、已被收下、已回覆、已下架）；按鈕「丟一個瓶子」連到 create，並顯示今日剩餘 `$throwsLeft`；表單 POST `member.bottles.pick` 按鈕「撿一個瓶子」，顯示今日剩餘 `$picksLeft`。
- `bottle-create.blade.php`：textarea `body`（`maxlength=300`）、核取方塊 `agree_conduct` 與連到 `route('conduct')`（英文為 `route('conduct.en')`，確認路由名稱後使用）的連結、提示「內容不可含網址與 Email，並需符合行為準則」。
- `bottle-show.blade.php`：收件人視角顯示 `$bottle->body`，已回覆就顯示自己的回覆，否則顯示回覆表單，另有「檢舉」表單（`target=bottle`、選填 `reason`）。寄件者視角顯示自己的 `body`，已下架顯示 `__('bottles.show.removed')` 且不顯示內容，有回覆且未被下架就顯示回覆並提供「檢舉回覆」表單（`target=reply`），回覆被下架顯示 `__('bottles.show.reply_removed')`。所有視角都不輸出任何對方的資料。

`lang/zh_TW/bottles.php` 需要的鍵：`index.title`、`index.heading`、`index.received`、`index.sent`、`index.throw`、`index.pick`、`index.throws_left`、`index.picks_left`、`index.empty_received`、`index.empty_sent`；`status.delivered`、`status.opened`、`status.replied`、`status.in_sea`、`status.removed`；`create.title`、`create.heading`、`create.body`、`create.agree`、`create.conduct_link`、`create.hint`、`create.submit`；`show.title`、`show.heading`、`show.reply_heading`、`show.reply_submit`、`show.your_reply`、`show.their_reply`、`show.report`、`show.report_reply`、`show.reason`、`show.removed`、`show.reply_removed`、`show.waiting`；`errors.contact`、`errors.daily_limit`、`errors.holding`、`errors.pick_limit`、`errors.cannot_reply`；`flash.thrown`、`flash.empty_sea`、`flash.reported`；`mail.received.subject`、`intro`、`action`、`outro`；`mail.replied.subject`、`intro`、`action`、`outro`。`lang/en/bottles.php` 提供相同鍵的英文，`index.heading` 為 `Drift bottles`。

`center.blade.php` 在未讀訊息之後加入：

```blade
<a href="{{ route('member.bottles'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
   data-bottle-holding="{{ \App\Models\DriftBottle::heldCount($member) }}"
   class="block font-semibold text-moss underline underline-offset-4">
    {{ __('bottles.index.heading') }}
</a>
```

- [ ] **Step 4: 通知類別**

`DriftBottleReceivedNotification` 與 `DriftBottleRepliedNotification`，各自 `__construct(public string $url)`，`via` 回傳 `['mail']`，`toMail` 使用 `bottles.mail.received.*` 與 `bottles.mail.replied.*`，內容只有固定文案與按鈕連結，不含任何寄件者或內文。

- [ ] **Step 5: 驗證通過並 Commit**

Run: `php artisan test --compact tests/Feature/Bottles`
Expected: PASS。

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Add the member drift bottle flow"
```

---

### Task 3: 後台

**Files:**
- Create: `app/Filament/Resources/DriftBottles/*`
- Test: `tests/Feature/Bottles/DriftBottleAdminTest.php`

**Interfaces:**
- Consumes: `DriftBottle::removeBottle/restoreBottle/removeReply/restoreReply`、`DriftBottleReport::review`
- Produces: `DriftBottleResource`，頁面 `ListDriftBottles`、`ViewDriftBottle`，關聯管理器 `ReportsRelationManager`

- [ ] **Step 1: 寫失敗的測試**

`setUp` 以 `User::factory()->create(['email' => 'flamerecca711@gmail.com'])` 登入。

```php
public function test_list_shows_bottles_and_filters_by_pending_reports(): void
{
    $plain = DriftBottle::factory()->create();
    $reported = DriftBottle::factory()->create();
    DriftBottleReport::factory()->create(['bottle_id' => $reported->id]);

    Livewire::test(ListDriftBottles::class)
        ->assertCanSeeTableRecords([$plain, $reported])
        ->filterTable('pending_reports', true)
        ->assertCanSeeTableRecords([$reported])
        ->assertCanNotSeeTableRecords([$plain]);
}

public function test_list_can_search_by_body_and_member(): void
{
    $sender = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com']);
    $match = DriftBottle::factory()->create(['sender_id' => $sender->id, 'body' => '咖啡瓶子']);
    $other = DriftBottle::factory()->create(['body' => '無關']);

    Livewire::test(ListDriftBottles::class)
        ->searchTable('咖啡')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other])
        ->searchTable('alice@example.com')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
}

public function test_the_view_page_shows_sender_and_recipient_to_the_admin(): void
{
    $sender = Member::factory()->create(['name' => 'Alice Wang']);
    $recipient = Member::factory()->create(['name' => 'Bob Lin']);
    $bottle = DriftBottle::factory()->create(['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'body' => '內容']);

    Livewire::test(ViewDriftBottle::class, ['record' => $bottle->getKey()])
        ->assertSee('Alice Wang')
        ->assertSee('Bob Lin')
        ->assertSee('內容');
}

public function test_remove_and_restore_actions(): void
{
    $bottle = DriftBottle::factory()->create();

    Livewire::test(ViewDriftBottle::class, ['record' => $bottle->getKey()])
        ->callAction('removeBottle')
        ->assertNotified();
    $this->assertNotNull($bottle->fresh()->removed_at);

    Livewire::test(ViewDriftBottle::class, ['record' => $bottle->getKey()])
        ->callAction('restoreBottle')
        ->assertNotified();
    $this->assertNull($bottle->fresh()->removed_at);
}

public function test_a_report_can_be_confirmed_or_dismissed(): void
{
    $bottle = DriftBottle::factory()->create(['removed_at' => now()]);
    $confirmed = DriftBottleReport::factory()->create(['bottle_id' => $bottle->id]);
    $dismissed = DriftBottleReport::factory()->create(['bottle_id' => $bottle->id]);

    Livewire::test(ReportsRelationManager::class, ['ownerRecord' => $bottle, 'pageClass' => ViewDriftBottle::class])
        ->callAction(TestAction::make('confirmViolation')->table($confirmed))
        ->callAction(TestAction::make('dismiss')->table($dismissed));

    $this->assertSame('violation', $confirmed->fresh()->outcome);
    $this->assertNotNull($confirmed->fresh()->reviewed_at);
    $this->assertSame('dismissed', $dismissed->fresh()->outcome);
    $this->assertNull($bottle->fresh()->removed_at);
}

public function test_the_resource_is_read_only(): void
{
    $this->assertFalse(DriftBottleResource::canCreate());
    $bottle = DriftBottle::factory()->create();
    $this->assertFalse(DriftBottleResource::canEdit($bottle));
    $this->assertFalse(DriftBottleResource::canDelete($bottle));
}
```

- [ ] **Step 2: 驗證失敗**

Run: `php artisan test --compact tests/Feature/Bottles/DriftBottleAdminTest.php`
Expected: FAIL，資源不存在。

- [ ] **Step 3: 實作**

以 `MessageResource` 與 `LoginLinksRelationManager` 為範本建立：

- `DriftBottleResource`：`navigationGroup = '會員'`、`navigationSort = 40`、`navigationIcon = Heroicon::OutlinedEnvelopeOpen`、標籤「漂流瓶」，`canCreate/canEdit/canDelete/canDeleteAny` 皆回傳 false，頁面 `index` 與 `view`，`getRelations()` 回傳 `[ReportsRelationManager::class]`。
- 列表欄位：寄件者姓名與收件人姓名（可搜尋姓名與 Email，寫法同 `MessagesTable`）、內容限 40 字可搜尋、狀態 badge（`status()`）、待處理檢舉數、建立時間。
- 篩選：`SelectFilter` 狀態（用 `scopes` 或 `query` 對應欄位）、`TernaryFilter::make('pending_reports')`，真值時 `whereHas('reports', fn ($q) => $q->whereNull('reviewed_at'))`、日期區間。
- 檢視頁 infolist：寄件者、收件人、內容、回覆、各時間欄位、狀態。
- `ViewDriftBottle::getHeaderActions()`：`removeBottle`（`removed_at` 為空才顯示）、`restoreBottle`（不為空才顯示）、`removeReply`（有回覆且未下架才顯示）、`restoreReply`，皆 `requiresConfirmation()` 並送出通知。
- `ReportsRelationManager`：`$relationship = 'reports'`、標題「檢舉紀錄」，欄位為檢舉人姓名、目標、原因、處理結果、處理時間，`recordActions` 有 `confirmViolation` 與 `dismiss`，只在 `isPending()` 時顯示，呼叫 `$record->review(DriftBottleReport::OUTCOME_VIOLATION|OUTCOME_DISMISSED, auth()->id())`。

- [ ] **Step 4: 驗證通過並 Commit**

Run: `php artisan test --compact tests/Feature/Bottles/DriftBottleAdminTest.php`
Expected: PASS。

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Add drift bottle moderation to the admin panel"
```

---

### Task 4: 整體驗證

**Files:**
- Modify: `routes/console.php`
- Test: 全部測試

- [ ] **Step 1: 排程與路由**

確認 `routes/console.php` 已有 `Schedule::command('drift-bottles:release')->hourly();`。

Run: `php artisan schedule:list`
Expected: 含 `drift-bottles:release`。

Run: `php artisan route:list --name=member.bottles`
Expected: 7 組路由，各含 `.en`。

- [ ] **Step 2: 全套測試與格式**

Run: `vendor/bin/pint --format agent && php artisan test --compact`
Expected: 全部 PASS，記錄測試數量。若 Pint 改動不相關檔案，還原它們。

- [ ] **Step 3: Commit 剩餘變更**

```bash
git status --short
git add -A && git commit -m "Schedule drift bottle release"
```

沒有變更則略過。

## Self-Review

- **Spec 覆蓋：** 丟出、指派、撿取、釋放、開啟與回覆、檢舉與下架、後台、通知、隱私與資料層分別在 Task 1 到 3，排程與全套驗證在 Task 4。
- **Placeholder 檢查：** 前台視圖與文案以結構與鍵清單描述，實作時依該清單與既有 `member.layout` 風格完成，不屬於未決事項。
- **型別一致：** `throwFrom`、`pick`、`open`、`replyFrom`、`reportBy`、`release`、`heldCount`、`throwsToday`、`picksLeft`、`pickKey`、`containsContactInfo` 在各任務使用一致。
- **規格差異：** 已在決策紀錄說明已開啟瓶子在收件人被刪除時的處理。
