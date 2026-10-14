# 會員好友階段一實作計畫

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 讓會員取得專屬的加好友連結與 QR Code，掃描或點擊後送出邀請，經對方接受成為好友，並可移除好友與封鎖。

**Architecture:** 在 `members` 新增 `friend_code`，新增 `friendships` 與 `member_blocks` 兩張表與對應模型，邀請、接受、移除與封鎖的規則集中在模型的靜態與實例方法。前台沿用既有的 `member` guard 與雙語路由，新增一組需驗證 Email 的路由與三個控制器。QR Code 由 `chillerlan/php-qrcode` 在伺服器端產生 SVG 資料位址，內嵌於頁面。

**Tech Stack:** PHP 8.5、Laravel 13、PHPUnit 12、SQLite、`chillerlan/php-qrcode` 5.0.5。

**Spec:** `docs/superpowers/specs/2026-10-14-friends-and-messages-design.md`，本計畫實作其第 4.1 到 4.4、5.1 到 5.3、6 與 6.1 節與第 11 節的階段一。階段二，短訊與後台，另開計畫。

## Global Constraints

- 只允許把 `chillerlan/php-qrcode` 變成直接相依性，版本維持鎖定的 5.0.5，不下載任何其他新套件，不建立新的 `app` 底下的基礎資料夾。`app/View/Components` 是 Laravel 標準位置。
- 模型使用 `#[Fillable([...])]` 與 `#[Hidden([...])]` 屬性，見 `.ai/rules/models.md`。
- 所有檔案以 `php artisan make:` 指令建立，並傳入 `--no-interaction`。
- 所有測試使用 PHPUnit 類別，使用 `RefreshDatabase`，建立資料一律用 factory，前台頁面測試在 `setUp()` 呼叫 `$this->withoutVite()`。
- 路由命名：中文版 `member.<name>`，英文版 `member.<name>.en`，英文路徑加 `/en`，套用 `member.locale`、`member.active`。
- 好友頁面路由一律需要 `auth:member` 與 `member.verified`。
- 加好友連結的 `friend_code` 為 32 個小寫英數字，不顯示 Email。
- 加好友頁在連結不存在、擁有者停用、任一方封鎖時一律回應 404，不顯示原因。
- 前台文案放入 `lang/zh_TW/member.php` 與 `lang/en/member.php`，台灣用語，中英文之間留半形空格，中文句中不使用括號補充說明。
- 每次修改 PHP 檔案後，在 commit 前執行 `vendor/bin/pint --dirty --format agent`。
- 每次更新測試後執行該單一測試，不先跑整個測試套件。

## Review Focus

1. 重新產生連結後，舊連結與舊 QR Code 必須立即回應 404，Task 2 與 Task 6 測試。
2. 同一對會員不論誰先邀請、同時邀請，都只會有一列，且對方已邀請時視為接受，Task 4 測試。
3. 封鎖會刪除好友關係與待處理邀請，被封鎖者打開對方的加好友頁得到 404，Task 4 與 Task 6 測試。
4. 對不屬於自己的關係做接受、忽略、移除一律 404，用 id 探測不到別人的資料，Task 7 測試。
5. 未登入打開加好友連結，登入後必須回到該頁，密碼與登入連結兩種登入方式都要成立，Task 6 與 Task 8 測試。測試以 `withSession(['url.intended' => ...])` 模擬，因為測試用戶端不會在請求之間保留 session。
6. 已停用的會員不出現在任何人的清單，也不能被邀請，Task 6 與 Task 7 測試。

---

## File Structure

| 檔案 | 責任 |
| :---- | :---- |
| `composer.json` | 修改，`chillerlan/php-qrcode` 列為直接相依性 |
| `app/View/Components/QrCode.php`、`resources/views/components/qr-code.blade.php` | QR Code 元件 |
| `database/migrations/*_add_friend_code_to_members_table.php` | 新增並補齊 `friend_code` |
| `database/migrations/*_create_friendships_table.php`、`*_create_member_blocks_table.php` | 好友關係與封鎖 |
| `app/Models/Friendship.php`、`app/Models/MemberBlock.php` | 規則與查詢 |
| `app/Models/Member.php` | 修改，`friend_code` 與好友輔助方法 |
| `database/factories/FriendshipFactory.php`、`MemberBlockFactory.php` | factory |
| `app/Http/Middleware/EnsureMemberEmailIsVerified.php` | `member.verified` |
| `app/Http/Controllers/Member/FriendController.php` | 我的好友、重新產生、接受、忽略與移除 |
| `app/Http/Controllers/Member/FriendInvitationController.php` | 加好友頁與送出邀請 |
| `app/Http/Controllers/Member/BlockController.php` | 封鎖與解除封鎖 |
| `app/Http/Controllers/Member/LoginLinkController.php`、`CenterController.php` | 修改，回到原頁與顯示好友入口 |
| `resources/views/member/friends.blade.php`、`friend-add.blade.php`、`center.blade.php` | 頁面 |
| `routes/web.php`、`bootstrap/app.php`、`app/Providers/AppServiceProvider.php` | 路由、別名與限流 |
| `lang/zh_TW/member.php`、`lang/en/member.php` | 文案 |
| `tests/Feature/Friends/*.php` | 對應各任務的測試 |

---

### Task 1: QR Code 元件與直接相依性

**Files:**
- Modify: `composer.json`、`composer.lock`
- Create: `app/View/Components/QrCode.php`、`resources/views/components/qr-code.blade.php`
- Test: `tests/Feature/Friends/QrCodeComponentTest.php`

**Interfaces:**
- Produces: `<x-qr-code :value="..." :label="..." />`，輸出一個 `<img>`，`src` 為 SVG 的 base64 資料位址，`alt` 為 `label`。`QrCode::dataUri(): string`。

- [ ] **Step 1: 列為直接相依性**

```bash
composer require chillerlan/php-qrcode:5.0.5 --no-interaction
git diff --stat composer.json composer.lock
```

Expected：`composer.json` 多一行 `chillerlan/php-qrcode`，`composer.lock` 只有 `content-hash` 變動，沒有任何套件的版本或來源改變。若 lock 出現其他套件的升級，執行 `git checkout composer.lock` 還原，改用 `composer require chillerlan/php-qrcode:5.0.5 --no-update` 後 `composer update --lock`，仍然不允許任何版本變動，並記錄 `Ruling:`。

- [ ] **Step 2: 產生檔案**

```bash
php artisan make:component QrCode --no-interaction
php artisan make:test Friends/QrCodeComponentTest --phpunit --no-interaction
```

- [ ] **Step 3: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Friends;

use App\View\Components\QrCode;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class QrCodeComponentTest extends TestCase
{
    public function test_it_renders_an_inline_svg_data_uri_with_the_label(): void
    {
        $html = Blade::render('<x-qr-code value="https://example.test/friends/add/abc" label="我的 QR Code" />');

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('alt="我的 QR Code"', $html);
        $this->assertStringContainsString('src="data:image/svg+xml;base64,', $html);
    }

    public function test_the_payload_is_a_valid_svg_and_makes_no_external_request(): void
    {
        $uri = (new QrCode('https://example.test/friends/add/abc'))->dataUri();

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);

        $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('http://', str_replace('http://www.w3.org/2000/svg', '', $svg));
    }

    public function test_different_values_produce_different_codes(): void
    {
        $this->assertNotSame(
            (new QrCode('https://example.test/a'))->dataUri(),
            (new QrCode('https://example.test/b'))->dataUri(),
        );
    }
}
```

- [ ] **Step 4: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Friends/QrCodeComponentTest.php`
Expected: FAIL，建構子不接受 `value`。

- [ ] **Step 5: 實作元件**

`app/View/Components/QrCode.php`：

```php
<?php

namespace App\View\Components;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as QrCodeGenerator;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class QrCode extends Component
{
    public function __construct(public string $value, public string $label = '') {}

    public function dataUri(): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'eccLevel' => EccLevel::M,
            'outputBase64' => true,
            'addQuietzone' => true,
        ]);

        return (new QrCodeGenerator($options))->render($this->value);
    }

    public function render(): View
    {
        return view('components.qr-code');
    }
}
```

`resources/views/components/qr-code.blade.php`：

```blade
<img src="{{ $dataUri() }}" alt="{{ $label }}" {{ $attributes->class('size-48 rounded-xl bg-white p-3') }}>
```

- [ ] **Step 6: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Friends/QrCodeComponentTest.php`
Expected: PASS，3 個測試。

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add composer.json composer.lock app/View resources/views/components/qr-code.blade.php tests/Feature/Friends
git commit -m "Add QR code component and declare php-qrcode as a direct dependency"
```

---

### Task 2: `friend_code`

**Files:**
- Create: `database/migrations/*_add_friend_code_to_members_table.php`
- Modify: `app/Models/Member.php`
- Test: `tests/Feature/Friends/FriendCodeTest.php`

**Interfaces:**
- Produces: `Member::$friend_code`；`Member::newFriendCode(): string`；`Member::regenerateFriendCode(): void`；`Member::friendUrl(?string $locale = null): string`，產生 `member.friends.add[.en]` 的完整網址，路由於 Task 5 才存在，測試中以臨時路由代替。`friend_code` 不會出現在序列化。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:migration add_friend_code_to_members_table --no-interaction
php artisan make:test Friends/FriendCodeTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Friends;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FriendCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_members_get_a_unique_32_character_lowercase_code(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $a->friend_code);
        $this->assertNotSame($a->friend_code, $b->friend_code);
    }

    public function test_the_code_is_not_serialized(): void
    {
        $member = Member::factory()->create();

        $this->assertArrayNotHasKey('friend_code', $member->toArray());
        $this->assertStringNotContainsString($member->friend_code, $member->toJson());
    }

    public function test_regenerating_replaces_the_code(): void
    {
        $member = Member::factory()->create();
        $old = $member->friend_code;

        $member->regenerateFriendCode();

        $this->assertNotSame($old, $member->fresh()->friend_code);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $member->fresh()->friend_code);
    }

    public function test_friend_url_is_localized_and_contains_the_code(): void
    {
        Route::get('/friends/add/{code}', fn () => 'ok')->name('member.friends.add');
        Route::get('/en/friends/add/{code}', fn () => 'ok')->name('member.friends.add.en');
        Route::getRoutes()->refreshNameLookups();
        $member = Member::factory()->create();

        $this->assertSame(url('/friends/add/'.$member->friend_code), $member->friendUrl('zh_TW'));
        $this->assertSame(url('/en/friends/add/'.$member->friend_code), $member->friendUrl('en'));
    }

    public function test_the_migration_backfills_existing_members(): void
    {
        $migration = require glob(database_path('migrations/*_add_friend_code_to_members_table.php'))[0];
        $migration->down();

        DB::table('members')->insert([
            ['name' => 'A', 'email' => 'a@example.com', 'password' => 'x', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'B', 'email' => 'b@example.com', 'password' => 'x', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration->up();

        $codes = DB::table('members')->pluck('friend_code');
        $this->assertCount(2, $codes);
        $this->assertCount(2, $codes->unique());
        $codes->each(fn ($code) => $this->assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $code));
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Friends/FriendCodeTest.php`
Expected: FAIL，欄位不存在。

- [ ] **Step 4: 實作 migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('friend_code', 32)->nullable()->after('organization');
        });

        DB::table('members')->whereNull('friend_code')->orderBy('id')->each(function (object $member): void {
            DB::table('members')->where('id', $member->id)->update([
                'friend_code' => Str::lower(Str::random(32)),
            ]);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('friend_code', 32)->nullable(false)->change();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->unique('friend_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['friend_code']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('friend_code');
        });
    }
};
```

- [ ] **Step 5: 修改 `Member`**

`#[Hidden]` 改為 `#[Hidden(['password', 'remember_token', 'friend_code'])]`，頂端加入 `use Illuminate\Support\Str;`，並在 `casts()` 之前加入：

```php
protected static function booted(): void
{
    static::creating(function (Member $member): void {
        if (empty($member->friend_code)) {
            $member->friend_code = self::newFriendCode();
        }
    });
}

public static function newFriendCode(): string
{
    return Str::lower(Str::random(32));
}

public function regenerateFriendCode(): void
{
    $this->forceFill(['friend_code' => self::newFriendCode()])->save();
}

public function friendUrl(?string $locale = null): string
{
    $name = ($locale ?? app()->getLocale()) === 'en' ? 'member.friends.add.en' : 'member.friends.add';

    return route($name, ['code' => $this->friend_code]);
}
```

- [ ] **Step 6: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Friends/FriendCodeTest.php tests/Feature/Member`
Expected: PASS，階段一與階段二的會員測試都仍通過。

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Member.php database/migrations tests/Feature/Friends/FriendCodeTest.php
git commit -m "Add member friend code with backfill and regeneration"
```

---

### Task 3: `Friendship` 與 `MemberBlock` 資料層

**Files:**
- Create: `app/Models/Friendship.php`、`app/Models/MemberBlock.php`、`database/factories/FriendshipFactory.php`、`database/factories/MemberBlockFactory.php`
- Create: `database/migrations/*_create_friendships_table.php`、`*_create_member_blocks_table.php`
- Test: `tests/Feature/Friends/FriendshipModelTest.php`

**Interfaces:**
- Consumes: `Member`。
- Produces:
  - `Friendship` 常數 `STATUS_PENDING`、`STATUS_ACCEPTED`。
  - `Friendship::between(Member $a, Member $b): ?Friendship`。
  - `Friendship::between` 與工廠一律把較小的會員 id 放在 `member_low_id`。
  - `Friendship::scopeAccepted`、`scopePending`、`scopeInvolving(Member)`。
  - `isAccepted(): bool`、`isParticipant(Member): bool`、`otherMember(Member): Member`、`low()`、`high()`、`requester()`。
  - `MemberBlock::blocker()`、`blocked()`。
  - `MemberLoginLink` 不受影響。
  - factory：`FriendshipFactory::between(Member, Member)`、`accepted()`。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:model Friendship -mf --no-interaction
php artisan make:model MemberBlock -mf --no-interaction
php artisan make:test Friends/FriendshipModelTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendshipModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_between_finds_the_pair_regardless_of_order(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $friendship = Friendship::factory()->between($a, $b)->create();

        $this->assertTrue(Friendship::between($a, $b)->is($friendship));
        $this->assertTrue(Friendship::between($b, $a)->is($friendship));
        $this->assertNull(Friendship::between($a, Member::factory()->create()));
    }

    public function test_the_lower_id_is_always_stored_first(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $friendship = Friendship::factory()->between($b, $a)->create();

        $this->assertSame($a->id, $friendship->member_low_id);
        $this->assertSame($b->id, $friendship->member_high_id);
        $this->assertSame($b->id, $friendship->requester_id);
    }

    public function test_a_pair_can_only_exist_once(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Friendship::factory()->between($b, $a)->create();
    }

    public function test_status_helpers_and_scopes(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $c = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();
        Friendship::factory()->between($a, $c)->create();

        $this->assertCount(1, Friendship::query()->accepted()->involving($a)->get());
        $this->assertCount(1, Friendship::query()->pending()->involving($a)->get());
        $this->assertCount(2, Friendship::query()->involving($a)->get());
        $this->assertCount(1, Friendship::query()->involving($b)->get());
    }

    public function test_participant_and_other_member(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $outsider = Member::factory()->create();
        $friendship = Friendship::factory()->between($a, $b)->create();

        $this->assertTrue($friendship->isParticipant($a));
        $this->assertTrue($friendship->isParticipant($b));
        $this->assertFalse($friendship->isParticipant($outsider));
        $this->assertTrue($friendship->otherMember($a)->is($b));
        $this->assertTrue($friendship->otherMember($b)->is($a));
    }

    public function test_rows_are_removed_with_the_member(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->create();
        MemberBlock::factory()->create(['blocker_id' => $a->id, 'blocked_id' => $b->id]);

        $a->delete();

        $this->assertDatabaseCount('friendships', 0);
        $this->assertDatabaseCount('member_blocks', 0);
    }

    public function test_a_block_pair_is_unique(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        MemberBlock::factory()->create(['blocker_id' => $a->id, 'blocked_id' => $b->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        MemberBlock::factory()->create(['blocker_id' => $a->id, 'blocked_id' => $b->id]);
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Friends/FriendshipModelTest.php`
Expected: FAIL，資料表不存在。

- [ ] **Step 4: 實作 migration**

`*_create_friendships_table.php` 的 `up()` 與 `down()`：

```php
public function up(): void
{
    Schema::create('friendships', function (Blueprint $table) {
        $table->id();
        $table->foreignId('member_low_id')->constrained('members')->cascadeOnDelete();
        $table->foreignId('member_high_id')->constrained('members')->cascadeOnDelete();
        $table->foreignId('requester_id')->constrained('members')->cascadeOnDelete();
        $table->string('status')->default('pending');
        $table->timestamp('responded_at')->nullable();
        $table->timestamps();

        $table->unique(['member_low_id', 'member_high_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('friendships');
}
```

`*_create_member_blocks_table.php`：

```php
public function up(): void
{
    Schema::create('member_blocks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('blocker_id')->constrained('members')->cascadeOnDelete();
        $table->foreignId('blocked_id')->constrained('members')->cascadeOnDelete();
        $table->timestamp('created_at')->useCurrent();

        $table->unique(['blocker_id', 'blocked_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('member_blocks');
}
```

- [ ] **Step 5: 實作模型**

`app/Models/Friendship.php`：

```php
<?php

namespace App\Models;

use Database\Factories\FriendshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_low_id', 'member_high_id', 'requester_id', 'status', 'responded_at'])]
class Friendship extends Model
{
    /** @use HasFactory<FriendshipFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function low(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_low_id');
    }

    public function high(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_high_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requester_id');
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function orderedIds(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    public static function between(Member $a, Member $b): ?self
    {
        [$low, $high] = self::orderedIds($a->getKey(), $b->getKey());

        return static::query()
            ->where('member_low_id', $low)
            ->where('member_high_id', $high)
            ->first();
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInvolving(Builder $query, Member $member): Builder
    {
        return $query->where(function (Builder $query) use ($member): void {
            $query->where('member_low_id', $member->getKey())
                ->orWhere('member_high_id', $member->getKey());
        });
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isParticipant(Member $member): bool
    {
        return in_array($member->getKey(), [$this->member_low_id, $this->member_high_id], true);
    }

    public function otherMember(Member $member): Member
    {
        return $this->member_low_id === $member->getKey() ? $this->high : $this->low;
    }
}
```

`app/Models/MemberBlock.php`：

```php
<?php

namespace App\Models;

use Database\Factories\MemberBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['blocker_id', 'blocked_id'])]
class MemberBlock extends Model
{
    /** @use HasFactory<MemberBlockFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'blocked_id');
    }
}
```

- [ ] **Step 6: 實作 factory**

`database/factories/FriendshipFactory.php`：

```php
<?php

namespace Database\Factories;

use App\Models\Friendship;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Friendship>
 */
class FriendshipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requester = Member::factory()->create();
        $other = Member::factory()->create();

        return $this->pair($requester, $other) + [
            'status' => Friendship::STATUS_PENDING,
            'responded_at' => null,
        ];
    }

    public function between(Member $requester, Member $other): static
    {
        return $this->state(fn (array $attributes) => $this->pair($requester, $other));
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Friendship::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);
    }

    /**
     * @return array{member_low_id: int, member_high_id: int, requester_id: int}
     */
    private function pair(Member $requester, Member $other): array
    {
        [$low, $high] = Friendship::orderedIds($requester->getKey(), $other->getKey());

        return [
            'member_low_id' => $low,
            'member_high_id' => $high,
            'requester_id' => $requester->getKey(),
        ];
    }
}
```

`database/factories/MemberBlockFactory.php`：

```php
<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberBlock>
 */
class MemberBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blocker_id' => Member::factory(),
            'blocked_id' => Member::factory(),
        ];
    }
}
```

- [ ] **Step 7: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Friends/FriendshipModelTest.php`
Expected: PASS，7 個測試。

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models database tests/Feature/Friends/FriendshipModelTest.php
git commit -m "Add friendships and member blocks"
```

---

### Task 4: 邀請、接受、移除與封鎖的規則

**Files:**
- Modify: `app/Models/Friendship.php`、`app/Models/MemberBlock.php`、`app/Models/Member.php`
- Test: `tests/Feature/Friends/FriendshipRulesTest.php`

**Interfaces:**
- Consumes: Task 3 的模型。
- Produces:
  - `Friendship::INVITE_INVITED = 'invited'`、`INVITE_ACCEPTED = 'accepted'`、`INVITE_ALREADY_FRIENDS = 'already_friends'`、`INVITE_ALREADY_INVITED = 'already_invited'`、`INVITE_BLOCKED = 'blocked'`、`INVITE_SELF = 'self'`。
  - `Friendship::invite(Member $from, Member $to): string`，回傳上述常數之一。
  - `Friendship::acceptBy(Member $member): bool`，只有被邀請者可以對 `pending` 接受。
  - `Friendship::removeBy(Member $member): bool`，任一方可以移除，用於忽略、取消與移除好友。
  - `MemberBlock::block(Member $blocker, Member $blocked): void`、`MemberBlock::unblock(Member $blocker, Member $blocked): void`。
  - `Member::hasBlocked(Member): bool`、`isBlockedBy(Member): bool`、`isFriendWith(Member): bool`、`canBlock(Member $target): bool`。

- [ ] **Step 1: 產生測試檔**

```bash
php artisan make:test Friends/FriendshipRulesTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendshipRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_inviting_creates_a_pending_friendship_from_the_inviter(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $this->assertSame(Friendship::INVITE_INVITED, Friendship::invite($a, $b));

        $friendship = Friendship::between($a, $b);
        $this->assertSame(Friendship::STATUS_PENDING, $friendship->status);
        $this->assertSame($a->id, $friendship->requester_id);
    }

    public function test_inviting_twice_does_not_create_a_second_row(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::invite($a, $b);

        $this->assertSame(Friendship::INVITE_ALREADY_INVITED, Friendship::invite($a, $b));
        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_inviting_someone_who_already_invited_you_accepts_it(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::invite($a, $b);

        $this->assertSame(Friendship::INVITE_ACCEPTED, Friendship::invite($b, $a));

        $friendship = Friendship::between($a, $b);
        $this->assertTrue($friendship->isAccepted());
        $this->assertNotNull($friendship->responded_at);
        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_existing_friends_are_not_invited_again(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();

        $this->assertSame(Friendship::INVITE_ALREADY_FRIENDS, Friendship::invite($a, $b));
        $this->assertSame(Friendship::INVITE_ALREADY_FRIENDS, Friendship::invite($b, $a));
    }

    public function test_you_cannot_invite_yourself(): void
    {
        $a = Member::factory()->create();

        $this->assertSame(Friendship::INVITE_SELF, Friendship::invite($a, $a));
        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_blocks_in_either_direction_prevent_invitations(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        MemberBlock::block($a, $b);

        $this->assertSame(Friendship::INVITE_BLOCKED, Friendship::invite($a, $b));
        $this->assertSame(Friendship::INVITE_BLOCKED, Friendship::invite($b, $a));
        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_only_the_invitee_can_accept(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $outsider = Member::factory()->create();
        Friendship::invite($a, $b);
        $friendship = Friendship::between($a, $b);

        $this->assertFalse($friendship->acceptBy($a));
        $this->assertFalse($friendship->acceptBy($outsider));
        $this->assertTrue($friendship->acceptBy($b));
        $this->assertTrue($friendship->fresh()->isAccepted());
    }

    public function test_accepting_twice_is_harmless(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::invite($a, $b);
        $friendship = Friendship::between($a, $b);

        $this->assertTrue($friendship->acceptBy($b));
        $this->assertFalse(Friendship::between($a, $b)->acceptBy($b));
    }

    public function test_either_participant_can_remove_but_outsiders_cannot(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $outsider = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();

        $this->assertFalse(Friendship::between($a, $b)->removeBy($outsider));
        $this->assertTrue(Friendship::between($a, $b)->removeBy($b));
        $this->assertNull(Friendship::between($a, $b));
    }

    public function test_declining_and_cancelling_delete_the_pending_row_and_allow_a_new_invitation(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::invite($a, $b);

        Friendship::between($a, $b)->removeBy($b);

        $this->assertSame(Friendship::INVITE_INVITED, Friendship::invite($a, $b));
    }

    public function test_blocking_removes_the_friendship_and_is_idempotent(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();

        MemberBlock::block($a, $b);
        MemberBlock::block($a, $b);

        $this->assertNull(Friendship::between($a, $b));
        $this->assertTrue($a->hasBlocked($b));
        $this->assertTrue($b->isBlockedBy($a));
        $this->assertFalse($b->hasBlocked($a));
        $this->assertDatabaseCount('member_blocks', 1);
    }

    public function test_unblocking_does_not_restore_the_friendship(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();
        MemberBlock::block($a, $b);

        MemberBlock::unblock($a, $b);

        $this->assertFalse($a->hasBlocked($b));
        $this->assertNull(Friendship::between($a, $b));
    }

    public function test_can_block_only_friends_and_pending_inviters(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create();
        $inviter = Member::factory()->create();
        $invitee = Member::factory()->create();
        $stranger = Member::factory()->create();
        Friendship::factory()->between($me, $friend)->accepted()->create();
        Friendship::factory()->between($inviter, $me)->create();
        Friendship::factory()->between($me, $invitee)->create();

        $this->assertTrue($me->canBlock($friend));
        $this->assertTrue($me->canBlock($inviter));
        $this->assertFalse($me->canBlock($invitee));
        $this->assertFalse($me->canBlock($stranger));
        $this->assertFalse($me->canBlock($me));
    }

    public function test_is_friend_with(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $c = Member::factory()->create();
        Friendship::factory()->between($a, $b)->accepted()->create();
        Friendship::factory()->between($a, $c)->create();

        $this->assertTrue($a->isFriendWith($b));
        $this->assertTrue($b->isFriendWith($a));
        $this->assertFalse($a->isFriendWith($c));
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Friends/FriendshipRulesTest.php`
Expected: FAIL，`invite` 不存在。

- [ ] **Step 4: 實作 `Friendship` 規則**

頂端加入 `use Illuminate\Support\Facades\DB;`。在 `STATUS_ACCEPTED` 之後加入：

```php
public const INVITE_INVITED = 'invited';

public const INVITE_ACCEPTED = 'accepted';

public const INVITE_ALREADY_FRIENDS = 'already_friends';

public const INVITE_ALREADY_INVITED = 'already_invited';

public const INVITE_BLOCKED = 'blocked';

public const INVITE_SELF = 'self';
```

並在 `otherMember()` 之後加入：

```php
public static function invite(Member $from, Member $to): string
{
    if ($from->is($to)) {
        return self::INVITE_SELF;
    }

    if ($from->hasBlocked($to) || $from->isBlockedBy($to)) {
        return self::INVITE_BLOCKED;
    }

    [$low, $high] = self::orderedIds($from->getKey(), $to->getKey());

    $friendship = static::query()->createOrFirst(
        ['member_low_id' => $low, 'member_high_id' => $high],
        ['requester_id' => $from->getKey(), 'status' => self::STATUS_PENDING],
    );

    if ($friendship->wasRecentlyCreated) {
        return self::INVITE_INVITED;
    }

    if ($friendship->isAccepted()) {
        return self::INVITE_ALREADY_FRIENDS;
    }

    if ($friendship->requester_id === $from->getKey()) {
        return self::INVITE_ALREADY_INVITED;
    }

    $friendship->acceptBy($from);

    return self::INVITE_ACCEPTED;
}

public function acceptBy(Member $member): bool
{
    if (! $this->isParticipant($member) || $this->requester_id === $member->getKey()) {
        return false;
    }

    $accepted = static::query()
        ->whereKey($this->getKey())
        ->where('status', self::STATUS_PENDING)
        ->update(['status' => self::STATUS_ACCEPTED, 'responded_at' => now()]);

    if ($accepted !== 1) {
        return false;
    }

    $this->refresh();

    return true;
}

public function removeBy(Member $member): bool
{
    if (! $this->isParticipant($member)) {
        return false;
    }

    return (bool) $this->delete();
}
```

`DB` 的 `use` 本任務不需要，不要加入，避免 pint 回報未使用的匯入。

- [ ] **Step 5: 實作 `MemberBlock` 規則**

頂端加入 `use Illuminate\Support\Facades\DB;`，並在類別尾端加入：

```php
public static function block(Member $blocker, Member $blocked): void
{
    DB::transaction(function () use ($blocker, $blocked): void {
        static::query()->firstOrCreate([
            'blocker_id' => $blocker->getKey(),
            'blocked_id' => $blocked->getKey(),
        ]);

        Friendship::between($blocker, $blocked)?->delete();
    });
}

public static function unblock(Member $blocker, Member $blocked): void
{
    static::query()
        ->where('blocker_id', $blocker->getKey())
        ->where('blocked_id', $blocked->getKey())
        ->delete();
}
```

- [ ] **Step 6: 修改 `Member`**

在 `loginLinks()` 之後加入：

```php
public function hasBlocked(Member $other): bool
{
    return MemberBlock::query()
        ->where('blocker_id', $this->getKey())
        ->where('blocked_id', $other->getKey())
        ->exists();
}

public function isBlockedBy(Member $other): bool
{
    return $other->hasBlocked($this);
}

public function isFriendWith(Member $other): bool
{
    return Friendship::between($this, $other)?->isAccepted() ?? false;
}

public function canBlock(Member $target): bool
{
    if ($this->is($target)) {
        return false;
    }

    $friendship = Friendship::between($this, $target);

    if ($friendship === null) {
        return false;
    }

    return $friendship->isAccepted() || $friendship->requester_id === $target->getKey();
}
```

- [ ] **Step 7: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Friends`
Expected: PASS，包含先前任務的測試。

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models tests/Feature/Friends/FriendshipRulesTest.php
git commit -m "Add invitation, accept, remove and block rules"
```

---

### Task 5: 驗證 middleware、路由表與文案

**Files:**
- Create: `app/Http/Middleware/EnsureMemberEmailIsVerified.php`、三個控制器 `FriendController`、`FriendInvitationController`、`BlockController`
- Modify: `bootstrap/app.php`、`app/Providers/AppServiceProvider.php`、`routes/web.php`、`lang/zh_TW/member.php`、`lang/en/member.php`
- Test: `tests/Feature/Friends/FriendRoutesTest.php`

**Interfaces:**
- Consumes: Task 2 的 `friendUrl`。
- Produces:
  - middleware 別名 `member.verified`。
  - rate limiter `member-invite`，每位已登入會員每小時 30 次。
  - 路由，名稱見下表，英文版加 `.en`。

| 名稱 | 方法與路徑 | 控制器方法 |
| :---- | :---- | :---- |
| `member.friends` | GET `/friends` | `FriendController@index` |
| `member.friends.code.regenerate` | POST `/friends/code` | `FriendController@regenerateCode` |
| `member.friends.add` | GET `/friends/add/{code}` | `FriendInvitationController@show` |
| `member.friends.invite` | POST `/friends/add/{code}` | `FriendInvitationController@store` |
| `member.friends.accept` | POST `/friends/{friendship}/accept` | `FriendController@accept` |
| `member.friends.decline` | DELETE `/friends/{friendship}` | `FriendController@destroy` |
| `member.friends.block` | POST `/friends/{member}/block` | `BlockController@store` |
| `member.friends.unblock` | DELETE `/friends/{member}/block` | `BlockController@destroy` |

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:middleware EnsureMemberEmailIsVerified --no-interaction
for c in Friend FriendInvitation Block; do php artisan make:controller "Member/${c}Controller" --no-interaction; done
php artisan make:test Friends/FriendRoutesTest --phpunit --no-interaction
```

三個控制器先改為 `extends MemberController` 的空類別，與階段二相同的作法。

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Friends;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FriendRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_friend_route_exists_in_both_languages(): void
    {
        $paths = [
            'member.friends' => '/friends',
            'member.friends.code.regenerate' => '/friends/code',
            'member.friends.add' => '/friends/add/{code}',
            'member.friends.invite' => '/friends/add/{code}',
            'member.friends.accept' => '/friends/{friendship}/accept',
            'member.friends.decline' => '/friends/{friendship}',
            'member.friends.block' => '/friends/{member}/block',
            'member.friends.unblock' => '/friends/{member}/block',
        ];

        foreach ($paths as $name => $path) {
            $this->assertSame($path, '/'.Route::getRoutes()->getByName($name)->uri());
            $this->assertSame('/en'.$path, '/'.Route::getRoutes()->getByName($name.'.en')->uri());
        }
    }

    public function test_friend_routes_require_login_and_verified_email(): void
    {
        $middleware = Route::getRoutes()->getByName('member.friends')->gatherMiddleware();

        $this->assertContains('auth:member', $middleware);
        $this->assertContains('member.verified', $middleware);
        $this->assertContains('member.locale:zh_TW', $middleware);
    }

    public function test_unverified_members_are_sent_to_the_verification_notice(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->actingAs($member, 'member')->get('/friends')->assertRedirect('/email/verify');
        $this->actingAs($member, 'member')->get('/en/friends')->assertRedirect('/en/email/verify');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/friends')->assertRedirect('/login');
        $this->get('/en/friends')->assertRedirect('/en/login');
    }

    public function test_inviting_is_rate_limited_per_member(): void
    {
        $this->assertContains('throttle:member-invite', Route::getRoutes()->getByName('member.friends.invite')->gatherMiddleware());
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Friends/FriendRoutesTest.php`
Expected: FAIL，路由不存在。

- [ ] **Step 4: 實作 middleware、別名與限流**

`app/Http/Middleware/EnsureMemberEmailIsVerified.php`：

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = Auth::guard('member')->user();

        if ($member !== null && ! $member->hasVerifiedEmail()) {
            return redirect(route($request->is('en/*') ? 'member.verification.notice.en' : 'member.verification.notice'));
        }

        return $next($request);
    }
}
```

`bootstrap/app.php` 的 `alias` 陣列加入 `'member.verified' => \App\Http\Middleware\EnsureMemberEmailIsVerified::class,`。

`AppServiceProvider::boot()` 加入：

```php
RateLimiter::for('member-invite', fn (Request $request) => Limit::perHour(30)
    ->by('member-invite|'.($request->user('member')?->getKey() ?? $request->ip())));
```

- [ ] **Step 5: 路由表**

`routes/web.php` 的 `use` 區加入三個控制器，並在會員路由迴圈的 `Route::middleware('auth:member')` 群組之後、`Route::get('/email/verify/{id}/{hash}'...)` 之前加入：

```php
Route::middleware(['auth:member', 'member.verified'])->group(function () use ($s): void {
    Route::get('/friends', [FriendController::class, 'index'])->name('member.friends'.$s);
    Route::post('/friends/code', [FriendController::class, 'regenerateCode'])->name('member.friends.code.regenerate'.$s);
    Route::get('/friends/add/{code}', [FriendInvitationController::class, 'show'])->name('member.friends.add'.$s);
    Route::post('/friends/add/{code}', [FriendInvitationController::class, 'store'])->middleware('throttle:member-invite')->name('member.friends.invite'.$s);
    Route::post('/friends/{friendship}/accept', [FriendController::class, 'accept'])->name('member.friends.accept'.$s);
    Route::delete('/friends/{friendship}', [FriendController::class, 'destroy'])->name('member.friends.decline'.$s);
    Route::post('/friends/{member}/block', [BlockController::class, 'store'])->name('member.friends.block'.$s);
    Route::delete('/friends/{member}/block', [BlockController::class, 'destroy'])->name('member.friends.unblock'.$s);
});
```

- [ ] **Step 6: 文案**

在 `lang/zh_TW/member.php` 最上層新增：

```php
'friends' => [
    'title' => '我的好友 | 共聚年會 GatherConf 2026',
    'heading' => '我的好友',
    'my_code_heading' => '我的加好友連結',
    'my_code_intro' => '讓朋友掃描這個 QR Code，或把連結傳給對方，就能向你送出好友邀請。',
    'qr_alt' => '加好友 QR Code',
    'regenerate' => '重新產生連結',
    'regenerate_note' => '重新產生後，舊的連結與 QR Code 會立即失效。',
    'regenerated' => '已重新產生連結，舊的連結已失效。',
    'received_heading' => '收到的邀請',
    'sent_heading' => '送出的邀請',
    'friends_heading' => '好友',
    'blocked_heading' => '已封鎖',
    'empty_friends' => '還沒有好友，分享你的 QR Code 開始吧。',
    'empty_received' => '沒有待處理的邀請。',
    'empty_sent' => '沒有等待回覆的邀請。',
    'empty_blocked' => '沒有封鎖任何人。',
    'accept' => '接受',
    'decline' => '忽略',
    'cancel' => '取消邀請',
    'remove' => '移除好友',
    'block' => '封鎖',
    'unblock' => '解除封鎖',
    'accepted' => '已成為好友。',
    'declined' => '已處理。',
    'removed' => '已移除。',
    'blocked' => '已封鎖。',
    'unblocked' => '已解除封鎖。',
    'add' => [
        'title' => '加好友 | 共聚年會 GatherConf 2026',
        'heading' => '加為好友',
        'intro' => ':name 邀請你互相加為好友。',
        'organization' => '所屬社群或公司',
        'submit' => '送出好友邀請',
        'self' => '這是你自己的連結，分享給朋友吧。',
        'already_friends' => '你們已經是好友了。',
        'already_invited' => '邀請已送出，等待對方接受。',
        'invitation_received' => '對方已經邀請你，按下按鈕即可接受。',
        'accept_submit' => '接受邀請',
        'back_to_friends' => '回到我的好友',
    ],
    'results' => [
        'invited' => '好友邀請已送出。',
        'accepted' => '你們已經成為好友。',
        'already_friends' => '你們已經是好友了。',
        'already_invited' => '邀請已經送出，請等待對方接受。',
    ],
],
```

在 `lang/en/member.php` 最上層新增：

```php
'friends' => [
    'title' => 'My friends | GatherConf 2026',
    'heading' => 'My friends',
    'my_code_heading' => 'My add-friend link',
    'my_code_intro' => 'Let a friend scan this QR code, or send them the link, so they can send you a friend request.',
    'qr_alt' => 'Add friend QR code',
    'regenerate' => 'Generate a new link',
    'regenerate_note' => 'The old link and QR code stop working immediately.',
    'regenerated' => 'A new link has been generated. The old one no longer works.',
    'received_heading' => 'Invitations received',
    'sent_heading' => 'Invitations sent',
    'friends_heading' => 'Friends',
    'blocked_heading' => 'Blocked',
    'empty_friends' => 'No friends yet. Share your QR code to get started.',
    'empty_received' => 'No pending invitations.',
    'empty_sent' => 'No invitations waiting for a reply.',
    'empty_blocked' => 'You have not blocked anyone.',
    'accept' => 'Accept',
    'decline' => 'Ignore',
    'cancel' => 'Cancel invitation',
    'remove' => 'Remove friend',
    'block' => 'Block',
    'unblock' => 'Unblock',
    'accepted' => 'You are now friends.',
    'declined' => 'Done.',
    'removed' => 'Removed.',
    'blocked' => 'Blocked.',
    'unblocked' => 'Unblocked.',
    'add' => [
        'title' => 'Add friend | GatherConf 2026',
        'heading' => 'Add as a friend',
        'intro' => ':name invites you to become friends.',
        'organization' => 'Community or company',
        'submit' => 'Send friend request',
        'self' => 'This is your own link. Share it with your friends.',
        'already_friends' => 'You are already friends.',
        'already_invited' => 'Your invitation was sent. Waiting for them to accept.',
        'invitation_received' => 'They already invited you. Press the button to accept.',
        'accept_submit' => 'Accept invitation',
        'back_to_friends' => 'Back to my friends',
    ],
    'results' => [
        'invited' => 'Friend request sent.',
        'accepted' => 'You are now friends.',
        'already_friends' => 'You are already friends.',
        'already_invited' => 'The invitation was already sent. Please wait for them to accept.',
    ],
],
```

- [ ] **Step 7: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Friends/FriendRoutesTest.php tests/Feature/Member`
Expected: PASS。

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app bootstrap routes lang tests/Feature/Friends/FriendRoutesTest.php
git commit -m "Add friend routes, verified middleware and copy"
```

---

### Task 6: 加好友頁與送出邀請

**Files:**
- Modify: `app/Http/Controllers/Member/FriendInvitationController.php`
- Create: `resources/views/member/friend-add.blade.php`
- Test: `tests/Feature/Friends/FriendInvitationTest.php`

**Interfaces:**
- Consumes: Task 4 的 `Friendship::invite`、`hasBlocked`、`isBlockedBy`、`between`；Task 5 的路由與文案。
- Produces: `GET/POST /friends/add/{code}` 與英文版；送出成功後導向 `member.friends[.en]` 並帶 `status`。

- [ ] **Step 1: 寫失敗的測試**

```bash
php artisan make:test Friends/FriendInvitationTest --phpunit --no-interaction
```

```php
<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_add_page_shows_name_and_organization_but_not_email(): void
    {
        $owner = Member::factory()->create(['name' => '陳小華', 'organization' => 'Kotlin Taiwan', 'email' => 'secret@example.com']);
        $visitor = Member::factory()->create();

        $this->actingAs($visitor, 'member')->get('/friends/add/'.$owner->friend_code)
            ->assertOk()
            ->assertSee('陳小華')
            ->assertSee('Kotlin Taiwan')
            ->assertSee('送出好友邀請')
            ->assertDontSee('secret@example.com')
            ->assertSee('noindex', false);
    }

    public function test_english_add_page(): void
    {
        $owner = Member::factory()->create(['name' => 'Alice']);
        $visitor = Member::factory()->create();

        $this->actingAs($visitor, 'member')->get('/en/friends/add/'.$owner->friend_code)
            ->assertOk()
            ->assertSee('Send friend request')
            ->assertDontSee('送出好友邀請');
    }

    public function test_unknown_code_returns_404(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')->get('/friends/add/not-a-code')->assertNotFound();
    }

    public function test_a_regenerated_code_invalidates_the_old_link(): void
    {
        $owner = Member::factory()->create();
        $visitor = Member::factory()->create();
        $old = $owner->friend_code;

        $owner->regenerateFriendCode();

        $this->actingAs($visitor, 'member')->get('/friends/add/'.$old)->assertNotFound();
        $this->actingAs($visitor, 'member')->get('/friends/add/'.$owner->fresh()->friend_code)->assertOk();
    }

    public function test_inactive_owner_returns_404(): void
    {
        $owner = Member::factory()->inactive()->create();

        $this->actingAs(Member::factory()->create(), 'member')->get('/friends/add/'.$owner->friend_code)->assertNotFound();
    }

    public function test_blocks_in_either_direction_return_404(): void
    {
        $owner = Member::factory()->create();
        $blockedByOwner = Member::factory()->create();
        $blockerOfOwner = Member::factory()->create();
        MemberBlock::block($owner, $blockedByOwner);
        MemberBlock::block($blockerOfOwner, $owner);

        $this->actingAs($blockedByOwner, 'member')->get('/friends/add/'.$owner->friend_code)->assertNotFound();
        $this->actingAs($blockerOfOwner, 'member')->get('/friends/add/'.$owner->friend_code)->assertNotFound();
        $this->actingAs($blockedByOwner, 'member')->post('/friends/add/'.$owner->friend_code)->assertNotFound();
    }

    public function test_own_link_shows_a_notice_without_an_invite_button(): void
    {
        $me = Member::factory()->create();

        $this->actingAs($me, 'member')->get('/friends/add/'.$me->friend_code)
            ->assertOk()
            ->assertSee('這是你自己的連結')
            ->assertDontSee('送出好友邀請');
    }

    public function test_guests_are_sent_to_login_and_come_back_after_logging_in(): void
    {
        $owner = Member::factory()->create();
        $visitor = Member::factory()->create(['email' => 'v@example.com']);
        $path = '/friends/add/'.$owner->friend_code;

        $this->get($path)->assertRedirect('/login');

        $this->withSession(['url.intended' => url($path)])
            ->post('/login', ['email' => 'v@example.com', 'password' => 'password'])
            ->assertRedirect(url($path));
    }

    public function test_unverified_members_are_sent_to_verification_first(): void
    {
        $owner = Member::factory()->create();

        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/friends/add/'.$owner->friend_code)
            ->assertRedirect('/email/verify');
    }

    public function test_posting_creates_a_pending_invitation(): void
    {
        $owner = Member::factory()->create();
        $visitor = Member::factory()->create();

        $this->actingAs($visitor, 'member')->post('/friends/add/'.$owner->friend_code)
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.results.invited'));

        $friendship = Friendship::between($owner, $visitor);
        $this->assertSame(Friendship::STATUS_PENDING, $friendship->status);
        $this->assertSame($visitor->id, $friendship->requester_id);
    }

    public function test_posting_to_someone_who_already_invited_you_accepts(): void
    {
        $owner = Member::factory()->create();
        $visitor = Member::factory()->create();
        Friendship::invite($owner, $visitor);

        $this->actingAs($visitor, 'member')->post('/friends/add/'.$owner->friend_code)
            ->assertSessionHas('status', __('member.friends.results.accepted'));

        $this->assertTrue(Friendship::between($owner, $visitor)->isAccepted());
    }

    public function test_posting_twice_does_not_duplicate(): void
    {
        $owner = Member::factory()->create();
        $visitor = Member::factory()->create();

        $this->actingAs($visitor, 'member')->post('/friends/add/'.$owner->friend_code);
        $this->actingAs($visitor, 'member')->post('/friends/add/'.$owner->friend_code)
            ->assertSessionHas('status', __('member.friends.results.already_invited'));

        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_posting_to_your_own_code_is_rejected(): void
    {
        $me = Member::factory()->create();

        $this->actingAs($me, 'member')->post('/friends/add/'.$me->friend_code)->assertRedirect('/friends');

        $this->assertDatabaseCount('friendships', 0);
    }

    public function test_english_post_redirects_to_the_english_friends_page(): void
    {
        $owner = Member::factory()->create();

        $this->actingAs(Member::factory()->create(), 'member')
            ->post('/en/friends/add/'.$owner->friend_code)
            ->assertRedirect('/en/friends');
    }
}
```

- [ ] **Step 2: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Friends/FriendInvitationTest.php`
Expected: FAIL，控制器方法不存在。

- [ ] **Step 3: 實作控制器**

登入後回到原頁需要 `LoginController::store` 的 `redirect()->intended(...)`，階段二已實作。

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\Friendship;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FriendInvitationController extends MemberController
{
    public function show(string $code): View
    {
        $me = $this->guard()->user();
        $owner = $this->ownerFor($code, $me);

        $relation = 'none';

        if (! $owner->is($me)) {
            $friendship = Friendship::between($me, $owner);

            $relation = match (true) {
                $friendship === null => 'none',
                $friendship->isAccepted() => 'friends',
                $friendship->requester_id === $me->getKey() => 'sent',
                default => 'received',
            };
        }

        return view('member.friend-add', [
            'owner' => $owner,
            'isSelf' => $owner->is($me),
            'relation' => $relation,
            'code' => $code,
        ]);
    }

    public function store(string $code): RedirectResponse
    {
        $me = $this->guard()->user();
        $owner = $this->ownerFor($code, $me);

        if ($owner->is($me)) {
            return redirect($this->localizedRoute('member.friends'));
        }

        $result = Friendship::invite($me, $owner);

        abort_if($result === Friendship::INVITE_BLOCKED, 404);

        return redirect($this->localizedRoute('member.friends'))
            ->with('status', __('member.friends.results.'.$result));
    }

    private function ownerFor(string $code, Member $me): Member
    {
        $owner = Member::query()
            ->where('friend_code', $code)
            ->where('is_active', true)
            ->first();

        abort_if($owner === null, 404);
        abort_if(! $owner->is($me) && ($me->hasBlocked($owner) || $me->isBlockedBy($owner)), 404);

        return $owner;
    }
}
```

- [ ] **Step 4: 實作頁面**

`resources/views/member/friend-add.blade.php`：

```blade
@extends('member.layout')

@php
    $enUrl = route('member.friends.add.en', ['code' => $code]);
    $zhUrl = route('member.friends.add', ['code' => $code]);
    $s = app()->getLocale() === 'en' ? '.en' : '';
@endphp

@section('route', 'member.friends')
@section('zh_url', $zhUrl)
@section('en_url', $enUrl)
@section('robots', 'noindex')
@section('title', __('member.friends.add.title'))
@section('description', __('member.friends.add.title'))
@section('heading', __('member.friends.add.heading'))

@section('member_body')
    <div class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        <p class="text-xl font-bold">{{ $owner->name }}</p>
        @if ($owner->organization)
            <p class="text-sm text-ink/80">{{ __('member.friends.add.organization') }}：{{ $owner->organization }}</p>
        @endif

        @if ($isSelf)
            <p>{{ __('member.friends.add.self') }}</p>
        @elseif ($relation === 'friends')
            <p>{{ __('member.friends.add.already_friends') }}</p>
        @elseif ($relation === 'sent')
            <p>{{ __('member.friends.add.already_invited') }}</p>
        @else
            <p>{{ $relation === 'received' ? __('member.friends.add.invitation_received') : __('member.friends.add.intro', ['name' => $owner->name]) }}</p>
            <form method="POST" action="{{ route('member.friends.invite'.$s, ['code' => $code]) }}">
                @csrf
                <x-member-submit>
                    {{ $relation === 'received' ? __('member.friends.add.accept_submit') : __('member.friends.add.submit') }}
                </x-member-submit>
            </form>
        @endif

        <a href="{{ route('member.friends'.$s) }}" class="inline-block text-sm font-medium text-moss underline underline-offset-4">
            {{ __('member.friends.add.back_to_friends') }}
        </a>
    </div>
@endsection
```

`route` 區塊設為 `member.friends`，讓版面可以算出 `.en` 版本，語言切換則由 `zh_url` 與 `en_url` 覆寫。

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Friends/FriendInvitationTest.php`
Expected: PASS，15 個測試。`test_guests_are_sent_to_login_and_come_back_after_logging_in` 依賴 `LoginController::store` 的 intended 導向，若失敗，先確認登入頁是 `guest:member` 並且 `redirect()->guest()` 被呼叫。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources tests/Feature/Friends/FriendInvitationTest.php
git commit -m "Add the add-friend page and invitation endpoint"
```

---

### Task 7: 我的好友頁與管理動作

**Files:**
- Modify: `app/Http/Controllers/Member/FriendController.php`、`app/Http/Controllers/Member/BlockController.php`
- Create: `resources/views/member/friends.blade.php`
- Test: `tests/Feature/Friends/FriendManagementTest.php`

**Interfaces:**
- Consumes: Task 1 的 `<x-qr-code>`、Task 4 的規則、Task 5 的路由與文案。
- Produces: 「我的好友」頁、重新產生、接受、忽略與移除、封鎖與解除封鎖。

- [ ] **Step 1: 寫失敗的測試**

```bash
php artisan make:test Friends/FriendManagementTest --phpunit --no-interaction
```

```php
<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_page_shows_my_qr_code_and_link_in_both_languages(): void
    {
        $me = Member::factory()->create();

        $this->actingAs($me, 'member')->get('/friends')
            ->assertOk()
            ->assertSee('我的好友')
            ->assertSee(url('/friends/add/'.$me->friend_code), false)
            ->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee('noindex', false);

        $this->actingAs($me, 'member')->get('/en/friends')
            ->assertOk()
            ->assertSee('My friends')
            ->assertSee(url('/en/friends/add/'.$me->friend_code), false);
    }

    public function test_page_lists_friends_and_both_kinds_of_invitations(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create(['name' => '好友甲']);
        $inviter = Member::factory()->create(['name' => '邀請者乙']);
        $invitee = Member::factory()->create(['name' => '受邀者丙']);
        Friendship::factory()->between($me, $friend)->accepted()->create();
        Friendship::factory()->between($inviter, $me)->create();
        Friendship::factory()->between($me, $invitee)->create();

        $this->actingAs($me, 'member')->get('/friends')
            ->assertSee('好友甲')
            ->assertSee('邀請者乙')
            ->assertSee('受邀者丙');
    }

    public function test_inactive_members_are_hidden_from_every_list(): void
    {
        $me = Member::factory()->create();
        $gone = Member::factory()->inactive()->create(['name' => '已停用者']);
        Friendship::factory()->between($me, $gone)->accepted()->create();

        $this->actingAs($me, 'member')->get('/friends')->assertDontSee('已停用者');
    }

    public function test_other_members_data_is_not_shown(): void
    {
        $me = Member::factory()->create();
        $stranger = Member::factory()->create(['name' => '陌生人', 'email' => 'stranger@example.com']);
        $a = Member::factory()->create();
        Friendship::factory()->between($stranger, $a)->accepted()->create();

        $this->actingAs($me, 'member')->get('/friends')
            ->assertDontSee('陌生人')
            ->assertDontSee('stranger@example.com');
    }

    public function test_regenerating_the_code_changes_it(): void
    {
        $me = Member::factory()->create();
        $old = $me->friend_code;

        $this->actingAs($me, 'member')->post('/friends/code')
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.regenerated'));

        $this->assertNotSame($old, $me->fresh()->friend_code);
    }

    public function test_the_invitee_can_accept(): void
    {
        $me = Member::factory()->create();
        $inviter = Member::factory()->create();
        Friendship::invite($inviter, $me);
        $friendship = Friendship::between($inviter, $me);

        $this->actingAs($me, 'member')->post('/friends/'.$friendship->id.'/accept')
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.accepted'));

        $this->assertTrue($friendship->fresh()->isAccepted());
    }

    public function test_the_inviter_cannot_accept_their_own_invitation(): void
    {
        $me = Member::factory()->create();
        $invitee = Member::factory()->create();
        Friendship::invite($me, $invitee);
        $friendship = Friendship::between($me, $invitee);

        $this->actingAs($me, 'member')->post('/friends/'.$friendship->id.'/accept')->assertNotFound();

        $this->assertFalse($friendship->fresh()->isAccepted());
    }

    public function test_outsiders_get_404_for_accept_and_remove(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $outsider = Member::factory()->create();
        Friendship::invite($a, $b);
        $friendship = Friendship::between($a, $b);

        $this->actingAs($outsider, 'member')->post('/friends/'.$friendship->id.'/accept')->assertNotFound();
        $this->actingAs($outsider, 'member')->delete('/friends/'.$friendship->id)->assertNotFound();

        $this->assertNotNull($friendship->fresh());
    }

    public function test_either_side_can_ignore_cancel_or_remove(): void
    {
        $me = Member::factory()->create();
        $other = Member::factory()->create();
        Friendship::factory()->between($other, $me)->accepted()->create();

        $this->actingAs($me, 'member')->delete('/friends/'.Friendship::between($me, $other)->id)
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.removed'));

        $this->assertNull(Friendship::between($me, $other));
    }

    public function test_blocking_a_friend_removes_the_friendship_and_lists_them_as_blocked(): void
    {
        $me = Member::factory()->create();
        $friend = Member::factory()->create(['name' => '被封鎖者']);
        Friendship::factory()->between($me, $friend)->accepted()->create();

        $this->actingAs($me, 'member')->post('/friends/'.$friend->id.'/block')
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.blocked'));

        $this->assertNull(Friendship::between($me, $friend));
        $this->assertTrue($me->hasBlocked($friend));
        $this->actingAs($me, 'member')->get('/friends')->assertSee('被封鎖者');
    }

    public function test_blocking_a_pending_inviter_is_allowed(): void
    {
        $me = Member::factory()->create();
        $inviter = Member::factory()->create();
        Friendship::invite($inviter, $me);

        $this->actingAs($me, 'member')->post('/friends/'.$inviter->id.'/block')->assertRedirect('/friends');

        $this->assertTrue($me->hasBlocked($inviter));
        $this->assertNull(Friendship::between($me, $inviter));
    }

    public function test_blocking_a_stranger_by_id_returns_404(): void
    {
        $me = Member::factory()->create();
        $stranger = Member::factory()->create();

        $this->actingAs($me, 'member')->post('/friends/'.$stranger->id.'/block')->assertNotFound();

        $this->assertFalse($me->hasBlocked($stranger));
    }

    public function test_unblocking_removes_the_block_only_for_the_blocker(): void
    {
        $me = Member::factory()->create();
        $other = Member::factory()->create();
        MemberBlock::block($me, $other);

        $this->actingAs($other, 'member')->delete('/friends/'.$me->id.'/block')->assertNotFound();
        $this->assertTrue($me->hasBlocked($other));

        $this->actingAs($me, 'member')->delete('/friends/'.$other->id.'/block')
            ->assertRedirect('/friends')
            ->assertSessionHas('status', __('member.friends.unblocked'));

        $this->assertFalse($me->hasBlocked($other));
    }

    public function test_english_actions_redirect_to_the_english_page(): void
    {
        $me = Member::factory()->create();

        $this->actingAs($me, 'member')->post('/en/friends/code')->assertRedirect('/en/friends');
    }
}
```

- [ ] **Step 2: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Friends/FriendManagementTest.php`
Expected: FAIL，控制器方法不存在。

- [ ] **Step 3: 實作控制器**

`FriendController`：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\Friendship;
use App\Models\MemberBlock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FriendController extends MemberController
{
    public function index(): View
    {
        $me = $this->guard()->user();

        $friendships = Friendship::query()
            ->involving($me)
            ->with(['low', 'high'])
            ->latest('updated_at')
            ->get()
            ->filter(fn (Friendship $friendship) => $friendship->otherMember($me)->is_active);

        $blocked = MemberBlock::query()
            ->where('blocker_id', $me->getKey())
            ->with('blocked')
            ->latest('created_at')
            ->get();

        return view('member.friends', [
            'member' => $me,
            'friendUrl' => $me->friendUrl(),
            'friends' => $friendships->filter->isAccepted()->values(),
            'received' => $friendships
                ->reject->isAccepted()
                ->filter(fn (Friendship $friendship) => $friendship->requester_id !== $me->getKey())
                ->values(),
            'sent' => $friendships
                ->reject->isAccepted()
                ->filter(fn (Friendship $friendship) => $friendship->requester_id === $me->getKey())
                ->values(),
            'blocked' => $blocked,
        ]);
    }

    public function regenerateCode(): RedirectResponse
    {
        $this->guard()->user()->regenerateFriendCode();

        return redirect($this->localizedRoute('member.friends'))->with('status', __('member.friends.regenerated'));
    }

    public function accept(Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->acceptBy($this->guard()->user()), 404);

        return redirect($this->localizedRoute('member.friends'))->with('status', __('member.friends.accepted'));
    }

    public function destroy(Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->removeBy($this->guard()->user()), 404);

        return redirect($this->localizedRoute('member.friends'))->with('status', __('member.friends.removed'));
    }
}
```

`BlockController`：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use App\Models\MemberBlock;
use Illuminate\Http\RedirectResponse;

class BlockController extends MemberController
{
    public function store(Member $member): RedirectResponse
    {
        $me = $this->guard()->user();

        abort_unless($me->canBlock($member), 404);

        MemberBlock::block($me, $member);

        return redirect($this->localizedRoute('member.friends'))->with('status', __('member.friends.blocked'));
    }

    public function destroy(Member $member): RedirectResponse
    {
        $me = $this->guard()->user();

        abort_unless($me->hasBlocked($member), 404);

        MemberBlock::unblock($me, $member);

        return redirect($this->localizedRoute('member.friends'))->with('status', __('member.friends.unblocked'));
    }
}
```

- [ ] **Step 4: 實作頁面**

`resources/views/member/friends.blade.php`：

```blade
@extends('member.layout')

@section('route', 'member.friends')
@section('robots', 'noindex')
@section('title', __('member.friends.title'))
@section('description', __('member.friends.title'))
@section('heading', __('member.friends.heading'))

@section('member_body')
    @php($s = app()->getLocale() === 'en' ? '.en' : '')

    <section class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        <h2 class="text-xl font-bold text-moss">{{ __('member.friends.my_code_heading') }}</h2>
        <p class="text-sm leading-relaxed text-ink/80">{{ __('member.friends.my_code_intro') }}</p>
        <x-qr-code :value="$friendUrl" :label="__('member.friends.qr_alt')" class="mx-auto" />
        <input type="text" readonly value="{{ $friendUrl }}" aria-label="{{ __('member.friends.my_code_heading') }}"
               class="w-full rounded-xl border border-ink/30 bg-parchment px-4 py-3 text-sm"
               onclick="this.select()">
        <form method="POST" action="{{ route('member.friends.code.regenerate'.$s) }}">
            @csrf
            <p class="mb-3 text-xs text-ink/70">{{ __('member.friends.regenerate_note') }}</p>
            <x-member-submit>{{ __('member.friends.regenerate') }}</x-member-submit>
        </form>
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('member.friends.received_heading') }}</h2>
        @forelse ($received as $friendship)
            @php($other = $friendship->otherMember($member))
            <div class="mt-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ $other->name }}</p>
                @if ($other->organization)
                    <p class="text-sm text-ink/70">{{ $other->organization }}</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('member.friends.accept'.$s, ['friendship' => $friendship]) }}">
                        @csrf
                        <x-member-submit>{{ __('member.friends.accept') }}</x-member-submit>
                    </form>
                    <form method="POST" action="{{ route('member.friends.decline'.$s, ['friendship' => $friendship]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-full border border-ink/30 px-6 py-3 font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.decline') }}</button>
                    </form>
                    <form method="POST" action="{{ route('member.friends.block'.$s, ['member' => $other]) }}">
                        @csrf
                        <button type="submit" class="rounded-full border border-ink/30 px-6 py-3 font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.block') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('member.friends.empty_received') }}</p>
        @endforelse
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('member.friends.friends_heading') }}</h2>
        @forelse ($friends as $friendship)
            @php($other = $friendship->otherMember($member))
            <div class="mt-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ $other->name }}</p>
                @if ($other->organization)
                    <p class="text-sm text-ink/70">{{ $other->organization }}</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('member.friends.decline'.$s, ['friendship' => $friendship]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-full border border-ink/30 px-6 py-3 font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.remove') }}</button>
                    </form>
                    <form method="POST" action="{{ route('member.friends.block'.$s, ['member' => $other]) }}">
                        @csrf
                        <button type="submit" class="rounded-full border border-ink/30 px-6 py-3 font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.block') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('member.friends.empty_friends') }}</p>
        @endforelse
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('member.friends.sent_heading') }}</h2>
        @forelse ($sent as $friendship)
            @php($other = $friendship->otherMember($member))
            <div class="mt-4 flex items-center justify-between gap-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ $other->name }}</p>
                <form method="POST" action="{{ route('member.friends.decline'.$s, ['friendship' => $friendship]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-full border border-ink/30 px-4 py-2 text-sm font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.cancel') }}</button>
                </form>
            </div>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('member.friends.empty_sent') }}</p>
        @endforelse
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('member.friends.blocked_heading') }}</h2>
        @forelse ($blocked as $block)
            <div class="mt-4 flex items-center justify-between gap-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ $block->blocked->name }}</p>
                <form method="POST" action="{{ route('member.friends.unblock'.$s, ['member' => $block->blocked]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-full border border-ink/30 px-4 py-2 text-sm font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.unblock') }}</button>
                </form>
            </div>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('member.friends.empty_blocked') }}</p>
        @endforelse
    </section>
@endsection
```

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Friends/FriendManagementTest.php`
Expected: PASS，14 個測試。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources tests/Feature/Friends/FriendManagementTest.php
git commit -m "Add my friends page with accept, remove and block actions"
```

---

### Task 8: 入口、登入連結回原頁與整體驗證

**Files:**
- Modify: `app/Http/Controllers/Member/LoginLinkController.php`、`app/Http/Controllers/Member/CenterController.php`、`resources/views/member/center.blade.php`、`lang/zh_TW/member.php`、`lang/en/member.php`
- Test: `tests/Feature/Friends/FriendEntryPointsTest.php`

**Interfaces:**
- Consumes: 前面全部任務。
- Produces: 會員中心的「我的好友」入口與待處理邀請數；以登入連結登入後回到原頁。

- [ ] **Step 1: 寫失敗的測試**

```bash
php artisan make:test Friends/FriendEntryPointsTest --phpunit --no-interaction
```

```php
<?php

namespace Tests\Feature\Friends;

use App\Models\Friendship;
use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_member_center_links_to_friends_in_both_languages(): void
    {
        $me = Member::factory()->create();

        $this->actingAs($me, 'member')->get('/member')->assertSee(route('member.friends'), false);
        $this->actingAs($me, 'member')->get('/en/member')->assertSee(route('member.friends.en'), false);
    }

    public function test_member_center_shows_the_pending_invitation_count(): void
    {
        $me = Member::factory()->create();
        Friendship::factory()->between(Member::factory()->create(), $me)->create();
        Friendship::factory()->between(Member::factory()->create(), $me)->create();
        Friendship::factory()->between($me, Member::factory()->create())->create();
        Friendship::factory()->between(Member::factory()->inactive()->create(), $me)->create();

        $this->actingAs($me, 'member')->get('/member')->assertSee('data-pending-invitations="2"', false);
    }

    public function test_a_login_link_returns_to_the_page_the_member_wanted(): void
    {
        $owner = Member::factory()->create();
        $member = Member::factory()->create();
        $path = '/friends/add/'.$owner->friend_code;

        $this->get($path)->assertRedirect('/login');

        $this->withSession(['url.intended' => url($path)])
            ->get('/login/link/'.MemberLoginLink::issueFor($member))
            ->assertRedirect(url($path));
    }

    public function test_a_login_link_without_a_target_goes_to_the_center(): void
    {
        $member = Member::factory()->create();

        $this->get('/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/member');
    }
}
```

- [ ] **Step 2: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Friends/FriendEntryPointsTest.php`
Expected: FAIL，中心頁沒有入口，連結登入不回原頁。

- [ ] **Step 3: 實作**

`LoginLinkController::consume` 的最後一行改為：

```php
return redirect()->intended($this->localizedRoute('member.center'));
```

`CenterController::show` 回傳改為：

```php
return view('member.center', [
    'member' => $member,
    'pendingInvitations' => Friendship::query()
        ->pending()
        ->involving($member)
        ->where('requester_id', '!=', $member->getKey())
        ->with(['low', 'high'])
        ->get()
        ->filter(fn (Friendship $friendship) => $friendship->otherMember($member)->is_active)
        ->count(),
]);
```

並加上 `use App\Models\Friendship;`。

`resources/views/member/center.blade.php` 在登出表單之前加入：

```blade
<a href="{{ route('member.friends'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
   data-pending-invitations="{{ $pendingInvitations }}"
   class="inline-flex items-center gap-2 font-semibold text-moss underline underline-offset-4">
    {{ __('member.center.friends') }}
    @if ($pendingInvitations > 0)
        <span class="rounded-full bg-moss px-2 py-0.5 text-xs text-on-accent">{{ $pendingInvitations }}</span>
    @endif
</a>
```

`lang/zh_TW/member.php` 的 `center` 陣列加入 `'friends' => '我的好友',`，`lang/en/member.php` 加入 `'friends' => 'My friends',`。

- [ ] **Step 4: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Friends`
Expected: PASS。

- [ ] **Step 5: 完整驗證**

Run: `php artisan test --compact`
Expected: 全部 PASS。

- [ ] **Step 6: 手動確認**

```bash
php artisan migrate --no-interaction
npm run build
```

用兩個瀏覽器分頁登入兩位會員，確認：

- 「我的好友」頁顯示 QR Code，可以用手機掃描並開啟加好友頁。
- 未登入打開連結，登入後回到加好友頁。
- 邀請、接受、移除、封鎖與解除封鎖在中英文頁面都可用。
- 淺色與深色模式下 QR Code 都可掃描。

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources lang tests/Feature/Friends/FriendEntryPointsTest.php
git commit -m "Add friends entry point and return to the intended page after login link"
```

---

## 不在本計畫內

- 短訊、已讀、未讀數、Email 提醒與後台審閱，屬於階段二。
- 會員的語系偏好、檢舉按鈕、好友的備註與分組。
- 收到邀請的 Email 通知，目前只在會員中心與「我的好友」頁顯示。
