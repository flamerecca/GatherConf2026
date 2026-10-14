# 胡蘿蔔點數系統階段一 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 會員完成站上行為或掃描現場任務 QR Code 就獲得胡蘿蔔，任務列表頁讓會員看到哪些行為會被獎勵，後台可建立任務並手動調整。

**Architecture:** 只增不改的 `carrot_transactions` 帳本是唯一真相，`members.carrots` 是同一個資料庫交易內更新的快取。所有寫入都經過 `CarrotLedger`，以唯一的 `dedupe_key` 保證同一件事只發放一次。站上行為由模型事件與既有流程呼叫 `awardQuietly`，發放失敗不影響原行為。

**Tech Stack:** Laravel 13、PHP 8.5、Filament 4、PHPUnit 12、`chillerlan/php-qrcode`，沿用 UUIDv7 主鍵。

**Spec:** `docs/superpowers/specs/2026-10-14-carrots-design.md`

## Global Constraints

- 主鍵一律 `HasUuids` 與 `foreignUuid`，不修改既有 migration，只新增。
- 模型的 mass assignment 使用 `#[Fillable([...])]` Attribute，不用 `protected $fillable`，見 `.ai/rules/models.md`。
- 所有金額運算在資料庫交易內，餘額不可為負，`dedupe_key` 唯一。
- 站上行為與現場任務的發放數量只來自 `config/carrots.php` 與 `carrot_tasks.reward`。
- 前台頁面繁體中文與英文各一組文案，路由 `member.<name>` 與 `.en`，並加 `noindex`。
- 現場任務的 `code` 只出現在後台，不得出現在任務列表頁與任何前台頁面。
- 後台 Filament 文字使用繁體中文，導覽群組為「會員」。
- 每個 PHP 修改後執行 `vendor/bin/pint --dirty --format agent`，測試使用 factory。
- 不新增依賴、不新增 `app/` 底下的基底資料夾。

## 決策紀錄

- `CarrotLedger` 放在 `app/Models/CarrotLedger.php`，因為專案規定不可未經核准新增 `app/` 基底資料夾。它是普通的 final 類別，不是 Eloquent 模型。若之後核准 `app/Services`，只需搬移與改命名空間。
- 站上現行沒有任何流程建立 `QuizAttempt`，所以 `complete_quiz` 掛在 `QuizAttempt` 的 `saved` 事件上，測驗功能上線時自動生效。
- 後台「標記為已驗證」會寫入 `email_verified_at`，因此也會發放 `verify_email`，視為合理。
- `robots.txt` 已禁止 `/member` 與 `/en/member` 前綴，`/member/tasks` 不需另外加。

## Review Focus

- 兩個請求同時掃同一個任務：只能發一次，餘額不重複增加。
- 掃描時任務剛好被停用、過期或尚未開始：不發放，顯示對應提示，不是 500。
- 管理員調整讓餘額剛好為 0 可以，變成 -1 必須被拒絕且帳本與餘額都不變。
- 已被停用的會員不能掃碼，沿用 `member.active`。
- 發放過程丟出例外時，原本的註冊、設定密碼、加好友、傳短信仍要成功。
- 會員被刪除後，帳本隨會員 `cascadeOnDelete`，任務被刪除時明細保留且 `task_id` 變 null。

---

## File Structure

| 檔案 | 責任 |
| :---- | :---- |
| `database/migrations/*_create_carrot_tables.php` | `carrot_tasks`、`carrot_transactions`、`members.carrots` |
| `app/Models/CarrotTask.php` | 現場任務，產生 `code`，判斷是否開放 |
| `app/Models/CarrotTransaction.php` | 帳本明細，唯讀語意 |
| `app/Models/CarrotLedger.php` | 唯一寫入入口：`award`、`awardQuietly`、`adjust`、`balance` |
| `config/carrots.php` | 站上行為的獎勵數量 |
| `app/Models/Member.php` | `carrotTransactions()`、更新事件發放 |
| `app/Models/QuizAttempt.php`、`Friendship.php`、`Message.php` | 發放掛勾 |
| `app/Http/Controllers/Member/CarrotTaskController.php` | 任務列表頁與掃碼完成 |
| `resources/views/member/tasks.blade.php`、`task-result.blade.php` | 前台頁面 |
| `lang/zh_TW/carrots.php`、`lang/en/carrots.php` | 文案 |
| `app/Filament/Resources/CarrotTasks/*` | 任務 CRUD 與 QR Code |
| `app/Filament/Resources/CarrotTransactions/*` | 唯讀明細列表 |
| `app/Filament/Resources/Members/*` | 餘額、調整動作、明細關聯 |
| `tests/Feature/Carrots/*` | 測試 |

---

### Task 1: 資料層與 CarrotLedger

**Files:**
- Create: `database/migrations/2026_10_14_050000_create_carrot_tables.php`
- Create: `app/Models/CarrotTask.php`、`app/Models/CarrotTransaction.php`、`app/Models/CarrotLedger.php`
- Create: `database/factories/CarrotTaskFactory.php`、`database/factories/CarrotTransactionFactory.php`
- Create: `config/carrots.php`
- Modify: `app/Models/Member.php`
- Test: `tests/Feature/Carrots/CarrotLedgerTest.php`

**Interfaces:**
- Produces: `CarrotLedger::award(Member $member, string $reason, int $amount, string $dedupeKey, ?CarrotTask $task = null): bool`
- Produces: `CarrotLedger::awardQuietly(...same): bool`
- Produces: `CarrotLedger::adjust(Member $member, int $amount, string $note, ?int $createdBy = null): CarrotTransaction`，餘額會小於 0 時拋出 `DomainException`
- Produces: `CarrotLedger::balance(Member $member): int`，帳本加總
- Produces: `CarrotLedger::siteRewards(): array<string, int>`，來自 `config('carrots.site')`
- Produces: `CarrotTask::isOpen(): bool`、`CarrotTask::scopeOpen()`、`Member::carrotTransactions(): HasMany`

- [ ] **Step 1: 建立 migration 與檔案骨架**

Run: `php artisan make:migration create_carrot_tables --no-interaction`，把檔名時間改為 `2026_10_14_050000`，內容：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrot_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('reward');
            $table->string('code', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('carrot_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('members')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reason', 40);
            $table->string('note')->nullable();
            $table->foreignUuid('task_id')->nullable()->constrained('carrot_tasks')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('dedupe_key', 120)->unique();
            $table->timestamps();

            $table->index(['member_id', 'created_at']);
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->unsignedInteger('carrots')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('carrots');
        });

        Schema::dropIfExists('carrot_transactions');
        Schema::dropIfExists('carrot_tasks');
    }
};
```

`config/carrots.php`：

```php
<?php

return [
    /*
     * Carrots granted for the one-off behaviours on the site, keyed by behaviour code.
     */
    'site' => [
        'verify_email' => 5,
        'set_password' => 3,
        'complete_quiz' => 5,
        'first_friend' => 5,
        'first_message' => 2,
    ],
];
```

`app/Models/CarrotTask.php`：

```php
<?php

namespace App\Models;

use Database\Factories\CarrotTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'description', 'reward', 'is_active', 'starts_at', 'ends_at'])]
class CarrotTask extends Model
{
    /** @use HasFactory<CarrotTaskFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::creating(function (CarrotTask $task): void {
            if (empty($task->code)) {
                $task->code = Str::lower(Str::random(32));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reward' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CarrotTransaction::class, 'task_id');
    }

    public function isOpen(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    /**
     * @param  Builder<CarrotTask>  $query
     * @return Builder<CarrotTask>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function dedupeKeyFor(Member $member): string
    {
        return 'task:'.$this->getKey().':'.$member->getKey();
    }
}
```

`app/Models/CarrotTransaction.php`：

```php
<?php

namespace App\Models;

use Database\Factories\CarrotTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'amount', 'reason', 'note', 'task_id', 'created_by', 'dedupe_key'])]
class CarrotTransaction extends Model
{
    /** @use HasFactory<CarrotTransactionFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(CarrotTask::class, 'task_id');
    }
}
```

`app/Models/CarrotLedger.php`：

```php
<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class CarrotLedger
{
    public const REASON_MANUAL = 'manual';

    public const REASON_TASK = 'task';

    /**
     * @return array<string, int>
     */
    public static function siteRewards(): array
    {
        return array_map('intval', config('carrots.site', []));
    }

    public static function siteDedupeKey(string $behaviour, Member $member): string
    {
        return $behaviour.':'.$member->getKey();
    }

    /**
     * Grant carrots once per dedupe key. Returns false when this key was already granted.
     */
    public static function award(Member $member, string $reason, int $amount, string $dedupeKey, ?CarrotTask $task = null): bool
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('An award must be a positive amount.');
        }

        try {
            return (bool) self::record($member, $amount, $reason, $dedupeKey, $task?->getKey(), null, null);
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * Same as award() but never lets a failure escape, for use inside other behaviours.
     */
    public static function awardQuietly(Member $member, string $reason, int $amount, string $dedupeKey, ?CarrotTask $task = null): bool
    {
        try {
            return self::award($member, $reason, $amount, $dedupeKey, $task);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * Admin adjustment. Every call is its own transaction and the balance may not go below zero.
     */
    public static function adjust(Member $member, int $amount, string $note, ?int $createdBy = null): CarrotTransaction
    {
        if ($amount === 0) {
            throw new InvalidArgumentException('An adjustment cannot be zero.');
        }

        return self::record($member, $amount, self::REASON_MANUAL, 'manual:'.str()->uuid7(), null, $note, $createdBy);
    }

    public static function balance(Member $member): int
    {
        return (int) CarrotTransaction::query()->where('member_id', $member->getKey())->sum('amount');
    }

    private static function record(
        Member $member,
        int $amount,
        string $reason,
        string $dedupeKey,
        ?string $taskId,
        ?string $note,
        ?int $createdBy,
    ): ?CarrotTransaction {
        return DB::transaction(function () use ($member, $amount, $reason, $dedupeKey, $taskId, $note, $createdBy): ?CarrotTransaction {
            $locked = Member::query()->whereKey($member->getKey())->lockForUpdate()->firstOrFail();

            if (CarrotTransaction::query()->where('dedupe_key', $dedupeKey)->exists()) {
                return null;
            }

            $balance = $locked->carrots + $amount;

            if ($balance < 0) {
                throw new DomainException('Carrot balance cannot go below zero.');
            }

            $transaction = CarrotTransaction::query()->create([
                'member_id' => $locked->getKey(),
                'amount' => $amount,
                'reason' => $reason,
                'note' => $note,
                'task_id' => $taskId,
                'created_by' => $createdBy,
                'dedupe_key' => $dedupeKey,
            ]);

            Member::query()->whereKey($locked->getKey())->update(['carrots' => $balance]);

            $member->setAttribute('carrots', $balance)->syncOriginalAttribute('carrots');

            return $transaction;
        });
    }
}
```

Factories：`CarrotTaskFactory` 的 `definition()` 回傳 `['name' => fake()->words(3, true), 'description' => fake()->sentence(), 'reward' => 3, 'is_active' => true, 'starts_at' => null, 'ends_at' => null]`，並提供 `inactive()`、`notStarted()` (`starts_at` 為 `now()->addDay()`)、`ended()` (`ends_at` 為 `now()->subMinute()`) 狀態。`CarrotTransactionFactory` 的 `definition()` 回傳 `['member_id' => Member::factory(), 'amount' => 1, 'reason' => 'manual', 'dedupe_key' => 'manual:'.fake()->uuid()]`。

`Member.php` 加入關聯與 import `Illuminate\Database\Eloquent\Relations\HasMany` 已存在：

```php
public function carrotTransactions(): HasMany
{
    return $this->hasMany(CarrotTransaction::class);
}
```

`Member` 的 `casts()` 加入 `'carrots' => 'integer'`。

- [ ] **Step 2: 寫失敗的測試**

`tests/Feature/Carrots/CarrotLedgerTest.php`，類別 `Tests\Feature\Carrots\CarrotLedgerTest`，使用 `RefreshDatabase`：

```php
public function test_award_writes_a_transaction_and_updates_the_balance(): void
{
    $member = Member::factory()->create();

    $this->assertTrue(CarrotLedger::award($member, 'verify_email', 5, 'verify_email:'.$member->id));

    $this->assertSame(5, $member->carrots);
    $this->assertSame(5, $member->fresh()->carrots);
    $this->assertDatabaseHas('carrot_transactions', [
        'member_id' => $member->id,
        'amount' => 5,
        'reason' => 'verify_email',
        'dedupe_key' => 'verify_email:'.$member->id,
    ]);
}

public function test_the_same_dedupe_key_is_granted_only_once(): void
{
    $member = Member::factory()->create();

    $this->assertTrue(CarrotLedger::award($member, 'verify_email', 5, 'k'));
    $this->assertFalse(CarrotLedger::award($member, 'verify_email', 5, 'k'));

    $this->assertSame(5, $member->fresh()->carrots);
    $this->assertSame(1, CarrotTransaction::query()->count());
}

public function test_a_non_positive_award_is_rejected(): void
{
    $this->expectException(InvalidArgumentException::class);

    CarrotLedger::award(Member::factory()->create(), 'x', 0, 'k');
}

public function test_award_quietly_swallows_failures(): void
{
    $member = Member::factory()->create();

    $this->assertFalse(CarrotLedger::awardQuietly($member, 'x', 0, 'k'));
    $this->assertSame(0, CarrotTransaction::query()->count());
}

public function test_adjust_can_reach_zero_but_not_go_below(): void
{
    $member = Member::factory()->create();
    CarrotLedger::award($member, 'x', 3, 'k');

    CarrotLedger::adjust($member, -3, '扣除', 1);
    $this->assertSame(0, $member->fresh()->carrots);

    try {
        CarrotLedger::adjust($member, -1, '再扣', 1);
        $this->fail('Expected DomainException.');
    } catch (DomainException) {
    }

    $this->assertSame(0, $member->fresh()->carrots);
    $this->assertSame(2, CarrotTransaction::query()->count());
}

public function test_each_adjustment_is_its_own_record_with_note_and_actor(): void
{
    $member = Member::factory()->create();

    CarrotLedger::adjust($member, 2, '活動獎勵', 7);
    CarrotLedger::adjust($member, 2, '活動獎勵', 7);

    $this->assertSame(4, $member->fresh()->carrots);
    $this->assertDatabaseHas('carrot_transactions', ['member_id' => $member->id, 'note' => '活動獎勵', 'created_by' => 7, 'reason' => 'manual']);
    $this->assertSame(2, $member->carrotTransactions()->count());
}

public function test_balance_equals_the_ledger_sum(): void
{
    $member = Member::factory()->create();
    CarrotLedger::award($member, 'a', 5, 'k1');
    CarrotLedger::award($member, 'b', 3, 'k2');
    CarrotLedger::adjust($member, -4, 'x');

    $this->assertSame(4, CarrotLedger::balance($member));
    $this->assertSame(4, $member->fresh()->carrots);
}

public function test_transactions_are_append_only(): void
{
    $member = Member::factory()->create();
    CarrotLedger::award($member, 'a', 5, 'k1');
    $transaction = CarrotTransaction::query()->firstOrFail();

    $this->assertFalse($transaction->update(['amount' => 99]));
    $this->assertFalse($transaction->delete());
    $this->assertSame(5, $transaction->fresh()->amount);
}

public function test_deleting_a_task_keeps_the_transactions(): void
{
    $member = Member::factory()->create();
    $task = CarrotTask::factory()->create();
    CarrotLedger::award($member, 'task', 3, $task->dedupeKeyFor($member), $task);

    $task->delete();

    $this->assertNull(CarrotTransaction::query()->firstOrFail()->task_id);
}

public function test_task_open_window(): void
{
    $this->assertTrue(CarrotTask::factory()->create()->isOpen());
    $this->assertFalse(CarrotTask::factory()->inactive()->create()->isOpen());
    $this->assertFalse(CarrotTask::factory()->notStarted()->create()->isOpen());
    $this->assertFalse(CarrotTask::factory()->ended()->create()->isOpen());
    $this->assertCount(1, CarrotTask::query()->open()->get());
}

public function test_a_task_gets_an_unguessable_code(): void
{
    $a = CarrotTask::factory()->create();
    $b = CarrotTask::factory()->create();

    $this->assertSame(32, strlen($a->code));
    $this->assertNotSame($a->code, $b->code);
}
```

- [ ] **Step 3: 驗證失敗**

Run: `php artisan test --compact tests/Feature/Carrots/CarrotLedgerTest.php`
Expected: 在建立檔案前 FAIL，缺少類別或資料表。

- [ ] **Step 4: 實作並驗證通過**

建立 Step 1 的所有檔案後執行：

Run: `php artisan test --compact tests/Feature/Carrots/CarrotLedgerTest.php`
Expected: PASS，10 個測試。

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database app config tests
git commit -m "Add the carrot ledger, tasks and member balance"
```

---

### Task 2: 站上行為自動發放

**Files:**
- Modify: `app/Models/Member.php`、`app/Models/QuizAttempt.php`、`app/Models/Friendship.php`、`app/Models/Message.php`
- Test: `tests/Feature/Carrots/SiteAwardsTest.php`

**Interfaces:**
- Consumes: `CarrotLedger::awardQuietly`、`CarrotLedger::siteRewards()`、`CarrotLedger::siteDedupeKey()`
- Produces: 行為代碼 `verify_email`、`set_password`、`complete_quiz`、`first_friend`、`first_message`，`dedupe_key` 為 `{代碼}:{member id}`

- [ ] **Step 1: 寫失敗的測試**

`tests/Feature/Carrots/SiteAwardsTest.php`，使用 `RefreshDatabase`。輔助方法：

```php
private function carrotsOf(Member $member): int
{
    return $member->fresh()->carrots;
}
```

測試：

```php
public function test_verifying_an_email_awards_once(): void
{
    $member = Member::factory()->unverified()->create();

    $member->markEmailAsVerified();

    $this->assertSame(config('carrots.site.verify_email'), $this->carrotsOf($member));

    $member->forceFill(['email_verified_at' => now()->addMinute()])->save();
    $this->assertSame(config('carrots.site.verify_email'), $this->carrotsOf($member));
}

public function test_creating_an_already_verified_member_awards_nothing(): void
{
    $member = Member::factory()->create();

    $this->assertSame(0, $this->carrotsOf($member));
}

public function test_setting_the_first_password_awards_once(): void
{
    $member = Member::factory()->passwordless()->create();

    $member->forceFill(['password' => 'a-new-password'])->save();

    $this->assertSame(config('carrots.site.set_password'), $this->carrotsOf($member));
}

public function test_changing_an_existing_password_awards_nothing(): void
{
    $member = Member::factory()->create();

    $member->forceFill(['password' => 'another-password'])->save();

    $this->assertSame(0, $this->carrotsOf($member));
}

public function test_completing_a_quiz_awards_once(): void
{
    PersonalityType::query()->create(['code' => 'SNHG', 'name' => '測試', 'summary' => '摘要', 'description' => '說明']);
    $member = Member::factory()->create();

    foreach ([1, 2] as $_) {
        QuizAttempt::query()->create([
            'uuid' => (string) Str::uuid(), 'user_id' => $member->id, 'scores' => [],
            'type_code' => 'SNHG', 'completed_at' => now(),
        ]);
    }

    $this->assertSame(config('carrots.site.complete_quiz'), $this->carrotsOf($member));
}

public function test_an_unfinished_or_anonymous_quiz_awards_nothing(): void
{
    PersonalityType::query()->create(['code' => 'SNHG', 'name' => '測試', 'summary' => '摘要', 'description' => '說明']);
    $member = Member::factory()->create();

    QuizAttempt::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => $member->id, 'scores' => [], 'type_code' => 'SNHG', 'completed_at' => null]);
    QuizAttempt::query()->create(['uuid' => (string) Str::uuid(), 'user_id' => null, 'scores' => [], 'type_code' => 'SNHG', 'completed_at' => now()]);

    $this->assertSame(0, $this->carrotsOf($member));
}

public function test_the_first_accepted_friendship_awards_both_members_once(): void
{
    $a = Member::factory()->create();
    $b = Member::factory()->create();
    $friendship = Friendship::factory()->between($a, $b)->create();

    $this->assertSame(0, $this->carrotsOf($a));
    $this->assertTrue($friendship->acceptBy($b));

    $this->assertSame(config('carrots.site.first_friend'), $this->carrotsOf($a));
    $this->assertSame(config('carrots.site.first_friend'), $this->carrotsOf($b));

    $c = Member::factory()->create();
    Friendship::factory()->between($c, $a)->create()->acceptBy($a);

    $this->assertSame(config('carrots.site.first_friend'), $this->carrotsOf($a));
    $this->assertSame(config('carrots.site.first_friend'), $this->carrotsOf($c));
}

public function test_a_refused_acceptance_awards_nothing(): void
{
    $a = Member::factory()->create();
    $b = Member::factory()->create();
    $friendship = Friendship::factory()->between($a, $b)->create();

    $this->assertFalse($friendship->acceptBy($a));
    $this->assertSame(0, $this->carrotsOf($a));
    $this->assertSame(0, $this->carrotsOf($b));
}

public function test_the_first_message_awards_the_sender_only_once(): void
{
    $a = Member::factory()->create();
    $b = Member::factory()->create();
    Friendship::factory()->between($a, $b)->accepted()->create();

    Message::sendFrom($a, $b, 'hi');
    Message::sendFrom($a, $b, 'again');

    $this->assertSame(config('carrots.site.first_message') + config('carrots.site.first_friend') * 0, $this->carrotsOf($a));
    $this->assertSame(0, $this->carrotsOf($b));
}

public function test_a_blocked_message_awards_nothing(): void
{
    $a = Member::factory()->create();
    $b = Member::factory()->create();

    $this->assertNull(Message::sendFrom($a, $b, 'hi'));
    $this->assertSame(0, $this->carrotsOf($a));
}

public function test_a_failing_award_does_not_break_the_behaviour(): void
{
    config(['carrots.site.set_password' => 0]);
    $member = Member::factory()->passwordless()->create();

    $this->assertTrue($member->forceFill(['password' => 'a-new-password'])->save());
    $this->assertTrue($member->fresh()->hasPassword());
    $this->assertSame(0, $this->carrotsOf($member));
}

public function test_the_login_link_flow_awards_the_verification(): void
{
    $member = Member::factory()->unverified()->passwordless()->create();
    $token = MemberLoginLink::issueFor($member);

    $this->get(route('member.login.link.consume', ['token' => $token]))->assertRedirect();

    $this->assertSame(config('carrots.site.verify_email'), $this->carrotsOf($member));
}
```

`test_the_first_message...` 的斷言請直接寫成 `config('carrots.site.first_message')`，不要保留 `* 0` 那段。

- [ ] **Step 2: 驗證失敗**

Run: `php artisan test --compact tests/Feature/Carrots/SiteAwardsTest.php`
Expected: 發放相關測試 FAIL，餘額為 0。

- [ ] **Step 3: 實作掛勾**

`Member::booted()` 在 `creating` 之後加入：

```php
static::updated(function (Member $member): void {
    $rewards = CarrotLedger::siteRewards();

    if ($member->wasChanged('email_verified_at') && $member->email_verified_at !== null) {
        CarrotLedger::awardQuietly($member, 'verify_email', $rewards['verify_email'] ?? 0, CarrotLedger::siteDedupeKey('verify_email', $member));
    }

    $hadPassword = ! in_array($member->getOriginal('password'), [null, ''], true);

    if ($member->wasChanged('password') && ! $hadPassword && $member->hasPassword()) {
        CarrotLedger::awardQuietly($member, 'set_password', $rewards['set_password'] ?? 0, CarrotLedger::siteDedupeKey('set_password', $member));
    }
});
```

`QuizAttempt`：

```php
protected static function booted(): void
{
    static::saved(function (QuizAttempt $attempt): void {
        if ($attempt->user_id === null || $attempt->completed_at === null) {
            return;
        }

        $member = $attempt->member;

        if ($member !== null) {
            CarrotLedger::awardQuietly($member, 'complete_quiz', CarrotLedger::siteRewards()['complete_quiz'] ?? 0, CarrotLedger::siteDedupeKey('complete_quiz', $member));
        }
    });
}
```

`Friendship::acceptBy()` 在 `$this->refresh();` 之後、`return true;` 之前：

```php
foreach ([$member, $other] as $participant) {
    CarrotLedger::awardQuietly(
        $participant,
        'first_friend',
        CarrotLedger::siteRewards()['first_friend'] ?? 0,
        CarrotLedger::siteDedupeKey('first_friend', $participant),
    );
}
```

`Message::sendFrom()` 改為：

```php
$message = static::query()->create([...]);

CarrotLedger::awardQuietly(
    $sender,
    'first_message',
    CarrotLedger::siteRewards()['first_message'] ?? 0,
    CarrotLedger::siteDedupeKey('first_message', $sender),
);

return $message;
```

`awardQuietly` 傳入 0 時 `award` 會拋 `InvalidArgumentException` 並被吞掉，這就是「發放失敗不影響原行為」的測試依據。

- [ ] **Step 4: 驗證通過並跑相關既有測試**

Run: `php artisan test --compact tests/Feature/Carrots tests/Feature/Friends tests/Feature/Messages tests/Feature/Member`
Expected: PASS。若既有測試因多出的交易列數失敗，只調整該測試的預期並在 commit 說明。

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app tests
git commit -m "Award carrots for the one-off behaviours on the site"
```

---

### Task 3: 現場任務掃碼完成

**Files:**
- Create: `app/Http/Controllers/Member/CarrotTaskController.php`
- Create: `resources/views/member/task-result.blade.php`
- Create: `lang/zh_TW/carrots.php`、`lang/en/carrots.php`
- Modify: `routes/web.php`、`app/Providers/AppServiceProvider.php`、`app/Models/CarrotTask.php`
- Test: `tests/Feature/Carrots/FieldTaskTest.php`

**Interfaces:**
- Consumes: `CarrotLedger::award`、`CarrotTask::isOpen()`、`CarrotTask::dedupeKeyFor()`
- Produces: 路由 `member.tasks.complete` 與 `.en`，路徑 `/member/tasks/{code}`；`CarrotTask::completeUrl(?string $locale = null): string`
- Produces: 限流器 `member-task`，每位會員每分鐘 20 次

- [ ] **Step 1: 寫失敗的測試**

`tests/Feature/Carrots/FieldTaskTest.php`：

```php
public function test_scanning_awards_the_reward_once(): void
{
    $member = Member::factory()->create();
    $task = CarrotTask::factory()->create(['reward' => 4]);

    $this->actingAs($member, 'member')->get($task->completeUrl())
        ->assertOk()
        ->assertSee(__('carrots.result.earned', ['reward' => 4]), false);

    $this->assertSame(4, $member->fresh()->carrots);

    $this->actingAs($member, 'member')->get($task->completeUrl())
        ->assertOk()
        ->assertSee(__('carrots.result.done'), false);

    $this->assertSame(4, $member->fresh()->carrots);
}

public function test_a_guest_is_sent_to_login_and_returns_to_the_task(): void
{
    $task = CarrotTask::factory()->create();

    $this->get($task->completeUrl())->assertRedirect(route('member.login'));
    $this->assertSame($task->completeUrl(), session('url.intended'));
}

public function test_a_closed_task_awards_nothing(): void
{
    $member = Member::factory()->create();

    foreach ([CarrotTask::factory()->inactive(), CarrotTask::factory()->notStarted(), CarrotTask::factory()->ended()] as $factory) {
        $task = $factory->create();

        $this->actingAs($member, 'member')->get($task->completeUrl())
            ->assertOk()
            ->assertSee(__('carrots.result.closed'), false);
    }

    $this->assertSame(0, $member->fresh()->carrots);
}

public function test_an_unknown_code_is_a_404(): void
{
    $this->actingAs(Member::factory()->create(), 'member')
        ->get(route('member.tasks.complete', ['code' => str_repeat('a', 32)]))
        ->assertNotFound();
}

public function test_an_unverified_member_is_redirected_and_awarded_nothing(): void
{
    $member = Member::factory()->unverified()->create();
    $task = CarrotTask::factory()->create();

    $this->actingAs($member, 'member')->get($task->completeUrl())
        ->assertRedirect(route('member.verification.notice'));
    $this->assertSame(0, $member->fresh()->carrots);
}

public function test_an_inactive_member_cannot_scan(): void
{
    $member = Member::factory()->inactive()->create();
    $task = CarrotTask::factory()->create();

    $this->actingAs($member, 'member')->get($task->completeUrl());

    $this->assertSame(0, $member->fresh()->carrots);
}

public function test_scanning_is_rate_limited(): void
{
    $member = Member::factory()->create();
    $task = CarrotTask::factory()->create();

    for ($i = 0; $i < 20; $i++) {
        $this->actingAs($member, 'member')->get($task->completeUrl())->assertOk();
    }

    $this->actingAs($member, 'member')->get($task->completeUrl())->assertStatus(429);
}

public function test_the_english_page_is_in_english(): void
{
    $member = Member::factory()->create();
    $task = CarrotTask::factory()->create(['reward' => 2]);

    $this->actingAs($member, 'member')->get($task->completeUrl('en'))
        ->assertOk()
        ->assertSee(trans('carrots.result.earned', ['reward' => 2], 'en'), false);
}

public function test_two_members_each_earn_from_the_same_task(): void
{
    $task = CarrotTask::factory()->create(['reward' => 3]);
    $a = Member::factory()->create();
    $b = Member::factory()->create();

    $this->actingAs($a, 'member')->get($task->completeUrl())->assertOk();
    $this->actingAs($b, 'member')->get($task->completeUrl())->assertOk();

    $this->assertSame(3, $a->fresh()->carrots);
    $this->assertSame(3, $b->fresh()->carrots);
}
```

測試之間若限流或 guard 殘留，依既有測試慣例在 `setUp` 或測試中呼叫 `Auth::forgetGuards()` 與 `RateLimiter::clear`。

- [ ] **Step 2: 驗證失敗**

Run: `php artisan test --compact tests/Feature/Carrots/FieldTaskTest.php`
Expected: FAIL，路由與方法不存在。

- [ ] **Step 3: 實作**

`AppServiceProvider::boot()` 加入：

```php
RateLimiter::for('member-task', fn (Request $request) => Limit::perMinute(20)
    ->by('member-task|'.($request->user('member')?->getKey() ?? $request->ip())));
```

`CarrotTask` 加入：

```php
public function completeUrl(?string $locale = null): string
{
    $name = ($locale ?? app()->getLocale()) === 'en' ? 'member.tasks.complete.en' : 'member.tasks.complete';

    return route($name, ['code' => $this->code]);
}
```

`routes/web.php` 在已驗證群組內、`messages.store` 之後加入：

```php
Route::get('/member/tasks', [CarrotTaskController::class, 'index'])->name('member.tasks'.$s);
Route::get('/member/tasks/{code}', [CarrotTaskController::class, 'complete'])->middleware('throttle:member-task')->name('member.tasks.complete'.$s);
```

並在檔案開頭 `use App\Http\Controllers\Member\CarrotTaskController;`。

`CarrotTaskController`：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\CarrotLedger;
use App\Models\CarrotTask;
use Illuminate\Contracts\View\View;

class CarrotTaskController extends MemberController
{
    public function complete(string $code): View
    {
        $member = $this->guard()->user();
        $task = CarrotTask::query()->where('code', $code)->firstOrFail();

        if (! $task->isOpen()) {
            return $this->result('closed', $task);
        }

        $earned = CarrotLedger::award(
            $member,
            CarrotLedger::REASON_TASK,
            $task->reward,
            $task->dedupeKeyFor($member),
            $task,
        );

        return $this->result($earned ? 'earned' : 'done', $task);
    }

    private function result(string $state, CarrotTask $task): View
    {
        return view('member.task-result', [
            'state' => $state,
            'task' => $task,
            'carrots' => $this->guard()->user()->fresh()->carrots,
        ]);
    }
}
```

`resources/views/member/task-result.blade.php`，沿用 `member.layout`：

```blade
@extends('member.layout')

@section('route', 'member.tasks')
@section('robots', 'noindex')
@section('title', __('carrots.result.title'))
@section('description', __('carrots.result.title'))
@section('heading', __('carrots.result.heading'))

@section('member_body')
    @php($s = app()->getLocale() === 'en' ? '.en' : '')
    <div class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6" data-task-result="{{ $state }}">
        <p class="text-lg font-semibold">{{ $task->name }}</p>
        <p>
            @if ($state === 'earned')
                {{ __('carrots.result.earned', ['reward' => $task->reward]) }}
            @elseif ($state === 'done')
                {{ __('carrots.result.done') }}
            @else
                {{ __('carrots.result.closed') }}
            @endif
        </p>
        <p class="text-sm text-ink/80">{{ __('carrots.balance', ['count' => $carrots]) }}</p>
        <a href="{{ route('member.tasks'.$s) }}" class="font-semibold text-moss underline underline-offset-4">{{ __('carrots.result.to_list') }}</a>
    </div>
@endsection
```

`lang/zh_TW/carrots.php`：

```php
<?php

return [
    'balance' => '目前有 :count 根胡蘿蔔',
    'result' => [
        'title' => '任務結果 | 共聚年會 GatherConf 2026',
        'heading' => '任務結果',
        'earned' => '完成任務，獲得 :reward 根胡蘿蔔！',
        'done' => '你已經完成過這個任務了。',
        'closed' => '這個任務目前沒有開放。',
        'to_list' => '查看所有任務',
    ],
];
```

`lang/en/carrots.php`：

```php
<?php

return [
    'balance' => 'You have :count carrots',
    'result' => [
        'title' => 'Task result | GatherConf 2026',
        'heading' => 'Task result',
        'earned' => 'Task complete, you earned :reward carrots!',
        'done' => 'You have already completed this task.',
        'closed' => 'This task is not open right now.',
        'to_list' => 'See all tasks',
    ],
];
```

後續 Task 4 會在這兩個檔案新增 `list` 區塊。

- [ ] **Step 4: 驗證通過**

Run: `php artisan test --compact tests/Feature/Carrots/FieldTaskTest.php`
Expected: PASS，9 個測試。

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes resources lang tests
git commit -m "Let members complete field tasks by scanning their QR code"
```

---

### Task 4: 任務列表頁與會員中心

**Files:**
- Modify: `app/Http/Controllers/Member/CarrotTaskController.php`、`app/Http/Controllers/Member/CenterController.php`
- Modify: `resources/views/member/center.blade.php`、`lang/zh_TW/carrots.php`、`lang/en/carrots.php`
- Create: `resources/views/member/tasks.blade.php`
- Test: `tests/Feature/Carrots/TaskListTest.php`

**Interfaces:**
- Consumes: `CarrotLedger::siteRewards()`、`CarrotLedger::siteDedupeKey()`、`CarrotTask::scopeOpen()`、`CarrotTask::dedupeKeyFor()`
- Produces: 路由 `member.tasks`，視圖 `member.tasks`；會員中心的 `data-carrots` 屬性

- [ ] **Step 1: 寫失敗的測試**

`tests/Feature/Carrots/TaskListTest.php`：

```php
public function test_the_list_shows_every_site_behaviour_with_its_reward(): void
{
    $member = Member::factory()->create();

    $response = $this->actingAs($member, 'member')->get(route('member.tasks'))->assertOk();

    foreach (config('carrots.site') as $code => $reward) {
        $response->assertSee(__("carrots.site.$code.name"), false)
            ->assertSee(__("carrots.site.$code.description"), false)
            ->assertSee('data-site-task="'.$code.'"', false)
            ->assertSee('data-reward="'.$reward.'"', false);
    }
}

public function test_done_and_todo_states_follow_the_ledger(): void
{
    $member = Member::factory()->unverified()->create();
    $member->markEmailAsVerified();
    $member->refresh();

    $this->actingAs($member, 'member')->get(route('member.tasks'))
        ->assertSee('data-site-task="verify_email" data-state="done"', false)
        ->assertSee('data-site-task="set_password" data-state="todo"', false);
}

public function test_only_open_field_tasks_are_listed_and_their_code_is_never_shown(): void
{
    $member = Member::factory()->create();
    $open = CarrotTask::factory()->create(['name' => '攤位打卡', 'description' => '到攤位掃描']);
    $closed = [
        CarrotTask::factory()->inactive()->create(['name' => '停用任務']),
        CarrotTask::factory()->notStarted()->create(['name' => '未開始任務']),
        CarrotTask::factory()->ended()->create(['name' => '已結束任務']),
    ];

    $response = $this->actingAs($member, 'member')->get(route('member.tasks'))
        ->assertOk()
        ->assertSee('攤位打卡')
        ->assertSee('到攤位掃描')
        ->assertDontSee($open->code)
        ->assertDontSee('/member/tasks/'.$open->code);

    foreach ($closed as $task) {
        $response->assertDontSee($task->name)->assertDontSee($task->code);
    }
}

public function test_a_field_task_shows_done_after_scanning(): void
{
    $member = Member::factory()->create();
    $task = CarrotTask::factory()->create();

    $this->actingAs($member, 'member')->get($task->completeUrl());

    $this->actingAs($member, 'member')->get(route('member.tasks'))
        ->assertSee('data-field-task="'.$task->id.'" data-state="done"', false);
}

public function test_the_header_shows_the_total(): void
{
    $member = Member::factory()->create();
    CarrotLedger::adjust($member, 7, '測試');

    $this->actingAs($member, 'member')->get(route('member.tasks'))
        ->assertSee('data-carrots="7"', false);
}

public function test_guests_and_unverified_members_are_redirected(): void
{
    $this->get(route('member.tasks'))->assertRedirect(route('member.login'));

    $this->actingAs(Member::factory()->unverified()->create(), 'member')
        ->get(route('member.tasks'))
        ->assertRedirect(route('member.verification.notice'));
}

public function test_the_english_list_is_in_english_and_noindex(): void
{
    $member = Member::factory()->create();

    $this->actingAs($member, 'member')->get(route('member.tasks.en'))
        ->assertOk()
        ->assertSee(trans('carrots.list.heading', [], 'en'), false)
        ->assertSee('noindex', false);
}

public function test_the_member_center_links_to_the_list_with_the_total(): void
{
    $member = Member::factory()->create();
    CarrotLedger::adjust($member, 3, '測試');

    $this->actingAs($member, 'member')->get(route('member.center'))
        ->assertSee('data-carrots="3"', false)
        ->assertSee(route('member.tasks'), false);
}
```

- [ ] **Step 2: 驗證失敗**

Run: `php artisan test --compact tests/Feature/Carrots/TaskListTest.php`
Expected: FAIL，缺少 `index` 方法與視圖。

- [ ] **Step 3: 實作**

`CarrotTaskController::index()`：

```php
public function index(): View
{
    $member = $this->guard()->user();

    $tasks = CarrotTask::query()->open()->orderBy('created_at')->get();

    $keys = collect(CarrotLedger::siteRewards())
        ->keys()
        ->map(fn (string $code) => CarrotLedger::siteDedupeKey($code, $member))
        ->merge($tasks->map(fn (CarrotTask $task) => $task->dedupeKeyFor($member)));

    $done = CarrotTransaction::query()
        ->where('member_id', $member->getKey())
        ->whereIn('dedupe_key', $keys)
        ->pluck('dedupe_key')
        ->flip();

    return view('member.tasks', [
        'member' => $member,
        'siteTasks' => collect(CarrotLedger::siteRewards())->map(fn (int $reward, string $code) => [
            'code' => $code,
            'reward' => $reward,
            'done' => $done->has(CarrotLedger::siteDedupeKey($code, $member)),
        ]),
        'fieldTasks' => $tasks->map(fn (CarrotTask $task) => [
            'task' => $task,
            'done' => $done->has($task->dedupeKeyFor($member)),
        ]),
    ]);
}
```

加入 `use App\Models\CarrotTransaction;`。

`resources/views/member/tasks.blade.php`，沿用 `member.layout`，`@section('route', 'member.tasks')`，標題與標題文案取自 `carrots.list.*`。內容：

```blade
@section('member_body')
    <p data-carrots="{{ $member->carrots }}" class="mt-6 text-lg font-semibold">
        🥕 {{ __('carrots.balance', ['count' => $member->carrots]) }}
    </p>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('carrots.list.site_heading') }}</h2>
        @foreach ($siteTasks as $row)
            <div data-site-task="{{ $row['code'] }}" data-state="{{ $row['done'] ? 'done' : 'todo' }}" data-reward="{{ $row['reward'] }}"
                 class="mt-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ __('carrots.site.'.$row['code'].'.name') }}</p>
                <p class="text-sm text-ink/70">{{ __('carrots.site.'.$row['code'].'.description') }}</p>
                <p class="mt-2 text-sm">
                    🥕 × {{ $row['reward'] }} ·
                    {{ $row['done'] ? __('carrots.list.done') : __('carrots.list.todo') }}
                </p>
            </div>
        @endforeach
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('carrots.list.field_heading') }}</h2>
        <p class="mt-2 text-sm text-ink/70">{{ __('carrots.list.field_intro') }}</p>
        @forelse ($fieldTasks as $row)
            <div data-field-task="{{ $row['task']->id }}" data-state="{{ $row['done'] ? 'done' : 'todo' }}"
                 class="mt-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ $row['task']->name }}</p>
                @if ($row['task']->description)
                    <p class="text-sm text-ink/70">{{ $row['task']->description }}</p>
                @endif
                <p class="mt-2 text-sm">
                    🥕 × {{ $row['task']->reward }} ·
                    {{ $row['done'] ? __('carrots.list.done') : __('carrots.list.todo') }}
                </p>
            </div>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('carrots.list.field_empty') }}</p>
        @endforelse
    </section>
@endsection
```

注意 `data-site-task="verify_email" data-state="done"` 的屬性順序必須與測試斷言一致，勿在兩者之間插入其他屬性。

`lang/zh_TW/carrots.php` 新增：

```php
'list' => [
    'title' => '任務與胡蘿蔔 | 共聚年會 GatherConf 2026',
    'heading' => '任務與胡蘿蔔',
    'site_heading' => '站上任務',
    'field_heading' => '年會現場任務',
    'field_intro' => '到年會現場掃描任務 QR Code，登入後就會自動獲得胡蘿蔔。',
    'field_empty' => '目前沒有開放的現場任務。',
    'done' => '已完成',
    'todo' => '未完成',
],
'site' => [
    'verify_email' => ['name' => '驗證 Email', 'description' => '完成 Email 驗證。'],
    'set_password' => ['name' => '設定密碼', 'description' => '第一次為帳號設定密碼。'],
    'complete_quiz' => ['name' => '完成人格測驗', 'description' => '第一次完成人格測驗。'],
    'first_friend' => ['name' => '交到第一位好友', 'description' => '和另一位會員互相成為好友。'],
    'first_message' => ['name' => '傳出第一則訊息', 'description' => '傳訊息給你的好友。'],
],
```

`lang/en/carrots.php` 新增對應的英文：標題 `Tasks and carrots | GatherConf 2026`、`Tasks and carrots`、`Site tasks`、`On-site tasks at the conference`、`Scan the task QR code at the venue and log in to earn carrots automatically.`、`No on-site tasks are open right now.`、`Done`、`To do`；站上任務為 `Verify your email`、`Set a password`、`Complete the personality quiz`、`Make your first friend`、`Send your first message`，說明各一句對應中文。

`CenterController::show()` 的 view 資料不必新增，會員中心視圖直接使用 `$member->carrots`。`center.blade.php` 在未讀訊息那一行之後加入：

```blade
<a href="{{ route('member.tasks'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
   data-carrots="{{ $member->carrots }}"
   class="block font-semibold text-moss underline underline-offset-4">
    🥕 {{ __('carrots.balance', ['count' => $member->carrots]) }} · {{ __('carrots.list.heading') }}
</a>
```

- [ ] **Step 4: 驗證通過**

Run: `php artisan test --compact tests/Feature/Carrots tests/Feature/Member/MemberRoutesTest.php`
Expected: PASS。

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources lang tests
git commit -m "Add the task list page so members see which actions earn carrots"
```

---

### Task 5: 後台任務、明細與手動調整

**Files:**
- Create: `app/Filament/Resources/CarrotTasks/*`（以 `php artisan make:filament-resource CarrotTask --view --no-interaction` 產生後修改）
- Create: `app/Filament/Resources/CarrotTransactions/*`
- Create: `app/Filament/Resources/Members/RelationManagers/CarrotTransactionsRelationManager.php`
- Modify: `app/Filament/Resources/Members/MemberResource.php`、`Schemas/MemberInfolist.php`、`Pages/ViewMember.php`、`Actions/MemberActions.php`
- Test: `tests/Feature/Carrots/CarrotAdminTest.php`

**Interfaces:**
- Consumes: `CarrotLedger::adjust`、`CarrotTask::completeUrl()`、`App\View\Components\QrCode::dataUri()`
- Produces: 後台資源 `CarrotTaskResource`、`CarrotTransactionResource`，動作 `MemberActions::adjustCarrots()`

- [ ] **Step 1: 寫失敗的測試**

`tests/Feature/Carrots/CarrotAdminTest.php`，`setUp` 以 `User::factory()->create(['email' => 'flamerecca711@gmail.com'])` 登入，與 `MessageResourceTest` 相同。測試：

```php
public function test_the_task_can_be_created_without_choosing_a_code(): void
{
    Livewire::test(CreateCarrotTask::class)
        ->fillForm(['name' => '攤位打卡', 'description' => '掃描', 'reward' => 3, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $task = CarrotTask::query()->firstOrFail();
    $this->assertSame('攤位打卡', $task->name);
    $this->assertSame(32, strlen($task->code));
}

public function test_the_reward_must_be_a_positive_integer(): void
{
    Livewire::test(CreateCarrotTask::class)
        ->fillForm(['name' => 'x', 'reward' => 0])
        ->call('create')
        ->assertHasFormErrors(['reward']);
}

public function test_the_code_cannot_be_edited(): void
{
    $task = CarrotTask::factory()->create();
    $code = $task->code;

    Livewire::test(EditCarrotTask::class, ['record' => $task->getKey()])
        ->fillForm(['name' => '新名稱', 'code' => 'hacked'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertSame($code, $task->fresh()->code);
    $this->assertSame('新名稱', $task->fresh()->name);
}

public function test_the_view_page_shows_the_scan_url_and_a_qr_code(): void
{
    $task = CarrotTask::factory()->create();

    Livewire::test(ViewCarrotTask::class, ['record' => $task->getKey()])
        ->assertOk()
        ->assertSee($task->completeUrl())
        ->assertSeeHtml('data:image/svg+xml');
}

public function test_the_transaction_list_is_read_only_and_filterable(): void
{
    $member = Member::factory()->create();
    CarrotLedger::award($member, 'verify_email', 5, 'k1');
    CarrotLedger::adjust($member, 2, '補發');

    Livewire::test(ListCarrotTransactions::class)
        ->assertOk()
        ->assertCanSeeTableRecords(CarrotTransaction::all())
        ->filterTable('reason', 'manual')
        ->assertCanSeeTableRecords(CarrotTransaction::query()->where('reason', 'manual')->get())
        ->assertCanNotSeeTableRecords(CarrotTransaction::query()->where('reason', 'verify_email')->get());

    $this->assertFalse(CarrotTransactionResource::canCreate());
}

public function test_the_adjust_action_adds_carrots_with_a_reason(): void
{
    $member = Member::factory()->create();

    Livewire::test(ViewMember::class, ['record' => $member->getKey()])
        ->callAction('adjustCarrots', ['amount' => 5, 'note' => '活動獎勵'])
        ->assertNotified();

    $this->assertSame(5, $member->fresh()->carrots);
    $this->assertDatabaseHas('carrot_transactions', ['member_id' => $member->id, 'note' => '活動獎勵', 'created_by' => auth()->id()]);
}

public function test_the_adjust_action_rejects_going_below_zero(): void
{
    $member = Member::factory()->create();

    Livewire::test(ViewMember::class, ['record' => $member->getKey()])
        ->callAction('adjustCarrots', ['amount' => -1, 'note' => '扣除'])
        ->assertNotified();

    $this->assertSame(0, $member->fresh()->carrots);
    $this->assertSame(0, CarrotTransaction::query()->count());
}

public function test_the_adjust_action_requires_a_reason_and_a_non_zero_amount(): void
{
    $member = Member::factory()->create();

    Livewire::test(ViewMember::class, ['record' => $member->getKey()])
        ->callAction('adjustCarrots', ['amount' => 0, 'note' => ''])
        ->assertHasActionErrors(['amount', 'note']);
}

public function test_the_member_page_shows_the_balance_and_history(): void
{
    $member = Member::factory()->create();
    CarrotLedger::adjust($member, 4, '補發');

    Livewire::test(ViewMember::class, ['record' => $member->getKey()])
        ->assertSee('4')
        ->assertSeeLivewire(CarrotTransactionsRelationManager::class);
}
```

- [ ] **Step 2: 驗證失敗**

Run: `php artisan test --compact tests/Feature/Carrots/CarrotAdminTest.php`
Expected: FAIL，資源與動作不存在。

- [ ] **Step 3: 實作**

1. 先用 `search-docs` 確認 Filament 4 的 `ViewEntry`、動作 `->schema()` 與唯讀資源寫法，再動手。
2. `php artisan make:filament-resource CarrotTask --view --no-interaction`，設定：
   - `navigationGroup = '會員'`、`navigationSort = 20`、`modelLabel = '胡蘿蔔任務'`、`navigationIcon = Heroicon::OutlinedGift`。
   - 表單欄位：`name` 必填、`description` Textarea、`reward` 必填 `numeric()->integer()->minValue(1)`、`is_active` Toggle 預設 true、`starts_at`、`ends_at` DateTimePicker。`code` 不在表單內，因此無法被編輯。
   - 檢視頁 infolist：名稱、說明、獎勵、狀態、期間、`scan_url` 文字（`->state(fn (CarrotTask $record) => $record->completeUrl())->copyable()`），以及 QR Code：

     ```php
     TextEntry::make('qr')
         ->label('QR Code')
         ->state(fn (CarrotTask $record): HtmlString => new HtmlString(
             '<img src="'.(new QrCode($record->completeUrl('zh_TW')))->dataUri().'" alt="QR Code" class="size-64 rounded-xl bg-white p-3">'
         ))
         ->html(),
     ```

   - 列表欄位：名稱、獎勵、啟用、期間、完成人次 `withCount('transactions')`。
   - 不提供刪除以外的額外動作，刪除保留，`task_id` 由外鍵變 null。
3. 唯讀明細：參考 `MessageResource` 建立 `CarrotTransactionResource`、`ListCarrotTransactions`，`canCreate`、`canEdit`、`canDelete`、`canDeleteAny` 皆回傳 false，只有 `index` 頁。欄位：會員姓名（可搜尋姓名與 Email）、數量、原因 badge、說明、來源任務、時間。`SelectFilter::make('reason')` 的選項為 `config('carrots.site')` 的鍵加上 `task` 與 `manual`。`navigationSort = 25`。
4. 會員頁：
   - `CarrotTransactionsRelationManager`（`$relationship = 'carrotTransactions'`、標題「胡蘿蔔明細」、唯讀，不提供任何 `recordActions` 與 `headerActions`，`defaultSort('created_at', 'desc')`），並加入 `MemberResource::getRelations()`。
   - `MemberInfolist` 新增 `TextEntry::make('carrots')->label('胡蘿蔔')`。
   - `MemberActions::adjustCarrots()`：

     ```php
     public static function adjustCarrots(): Action
     {
         return Action::make('adjustCarrots')
             ->label('調整胡蘿蔔')
             ->icon(Heroicon::OutlinedGift)
             ->schema([
                 TextInput::make('amount')
                     ->label('數量，正數增加、負數扣除')
                     ->required()
                     ->numeric()
                     ->integer()
                     ->rules(['not_in:0']),
                 TextInput::make('note')->label('原因')->required()->maxLength(255),
             ])
             ->action(function (array $data, Member $record): void {
                 try {
                     CarrotLedger::adjust($record, (int) $data['amount'], $data['note'], auth()->id());
                 } catch (DomainException) {
                     Notification::make()->title('扣除後胡蘿蔔會小於 0，未調整')->danger()->send();

                     return;
                 }

                 Notification::make()->title('已調整胡蘿蔔')->success()->send();
             });
     }
     ```

     加入 `ViewMember::getHeaderActions()`。

- [ ] **Step 4: 驗證通過**

Run: `php artisan test --compact tests/Feature/Carrots/CarrotAdminTest.php tests/Feature/Member/MemberResourceTest.php`
Expected: PASS。

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app tests
git commit -m "Add carrot task management and manual adjustment to the admin panel"
```

---

### Task 6: 整體驗證與資料填充

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`（僅在現有 seeder 已建立會員時）
- Test: 全部測試

**Interfaces:**
- Consumes: 前述全部

- [ ] **Step 1: 全套測試與格式**

Run: `vendor/bin/pint --format agent && php artisan test --compact`
Expected: 全部 PASS，記錄測試數量。

- [ ] **Step 2: 路由與 robots 檢查**

Run: `php artisan route:list --name=member.tasks`
Expected: 四條路由 `member.tasks`、`member.tasks.complete` 與 `.en`。

Run: `php artisan test --compact --filter=robots`
Expected: PASS，`/member` 與 `/en/member` 前綴已涵蓋任務頁。

- [ ] **Step 3: Seeder 檢查**

Run: `php artisan test --compact tests/Feature/Member/DatabaseSeederTest.php`
Expected: PASS，seeder 建立會員時 `carrots` 預設為 0。

- [ ] **Step 4: Commit 剩餘變更**

```bash
git status --short
git add -A && git commit -m "Finish carrots stage one verification"
```

若 Step 1 到 3 沒有任何變更則略過 commit。

## Self-Review

- **Spec 覆蓋：** 帳本與重複防護在 Task 1，五種站上行為在 Task 2，現場任務與限流在 Task 3，任務列表頁與會員中心在 Task 4，後台任務、QR、明細與調整在 Task 5，全套驗證在 Task 6。
- **Placeholder 檢查：** 無 TBD，各任務皆含測試與實作程式碼，英文文案只列出標題與關鍵字串的對應，實作時依中文一句對一句翻譯。
- **型別一致：** `award`、`awardQuietly`、`adjust`、`balance`、`siteRewards`、`siteDedupeKey`、`dedupeKeyFor`、`completeUrl` 於各任務使用一致。
- **規格差異：** 規格第 4.4 節的 `created_by` 以獨立欄位實作，與規格一致。
