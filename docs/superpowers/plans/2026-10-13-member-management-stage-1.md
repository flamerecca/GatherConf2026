# 會員管理系統階段一實作計畫

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 建立獨立的 `Member` 模型、認證組態與登入連結資料表，並在 Filament 後台提供會員的列表、檢視、編輯、停用與啟用、手動驗證，以及登入連結紀錄的檢視與作廢。

**Architecture:** `Member` 是獨立於 `User` 的 Authenticatable 模型，擁有獨立的 `member` guard、`members` provider 與 `members` password broker，後台管理者的 `web` guard 完全不動。後台以 Filament v4 的 `MemberResource` 管理會員，登入連結以 `LoginLinksRelationManager` 掛在會員頁面。前台註冊登入屬於階段二，不在本計畫內。

**Tech Stack:** PHP 8.5、Laravel 13、Filament 4.12、PHPUnit 12、SQLite。

**Spec:** `docs/superpowers/specs/2026-10-13-member-management-design.md`，本計畫實作其中第 4、5、6 節與第 10 節的階段一。

## Global Constraints

- 不新增任何 composer 依賴，不建立新的 `app` 底下的基礎資料夾，沿用 `app/Models`、`app/Filament`、`database/*`。
- 模型使用 `#[Fillable([...])]` 與 `#[Hidden([...])]` 屬性，不使用 `$fillable` 與 `$hidden` 屬性寫法，見 `.ai/rules/models.md`。
- 所有檔案以 `php artisan make:` 指令建立，並傳入 `--no-interaction`。
- 所有測試使用 PHPUnit 類別，不使用 Pest，使用 `RefreshDatabase`，建立資料一律用 factory。
- Filament v4 命名空間：動作用 `Filament\Actions\`，版面用 `Filament\Schemas\Components\`，表單欄位用 `Filament\Forms\Components\`，資訊清單用 `Filament\Infolists\Components\`，圖示用 `Filament\Support\Icons\Heroicon`。
- 每次修改 PHP 檔案後，在 commit 前執行 `vendor/bin/pint --dirty --format agent`。
- 每次更新測試後執行該單一測試，不先跑整個測試套件。
- 後台文字使用台灣正體中文，中英文之間留半形空格，句中不使用括號補充說明。
- 後台不提供會員的建立頁、刪除，也不顯示或修改密碼。
- `member_login_links` 只儲存標記的雜湊，後台任何畫面都不得顯示 `token_hash`。
- `quiz_attempts` 的欄位名稱維持 `user_id`，只改外鍵指向 `members`，見規格 4.5。

## Review Focus

1. 會員 Email 與後台管理者 `users` 的 Email 相同時，兩邊都必須能建立，互不衝突，Task 1 測試。
2. 登入連結過期時間剛好等於現在時，必須視為已過期，Task 3 測試。
3. 已使用或已過期的連結不可被作廢，作廢動作不得改動它的狀態，Task 3 與 Task 7 測試。
4. 刪除會員時，其登入連結一併刪除，而 `quiz_attempts` 的紀錄保留且 `user_id` 變為空，Task 4 測試。
5. 所屬社群或公司為空值的會員，列表、搜尋與檢視頁都不得出錯，Task 5 測試。
6. 已停用的會員不得再出現「停用」動作，已啟用的會員不得出現「啟用」動作，已驗證的會員不得出現「標記為已驗證」，Task 6 測試。

---

## File Structure

| 檔案 | 責任 |
| :---- | :---- |
| `app/Models/Member.php` | 會員模型，Authenticatable，實作 `MustVerifyEmail` |
| `app/Models/MemberLoginLink.php` | 登入連結紀錄模型，提供狀態計算與作廢 |
| `app/Models/QuizAttempt.php` | 修改，新增 `member()` 關聯 |
| `database/migrations/*_create_members_table.php` | 建立 `members` 與 `member_password_reset_tokens` |
| `database/migrations/*_create_member_login_links_table.php` | 建立 `member_login_links` |
| `database/migrations/*_point_quiz_attempts_user_id_to_members.php` | 把外鍵改指向 `members` |
| `database/factories/MemberFactory.php` | 會員 factory，含 `unverified()` 與 `inactive()` |
| `database/factories/MemberLoginLinkFactory.php` | 登入連結 factory，含 `used()`、`expired()`、`revoked()` |
| `database/seeders/MemberSeeder.php` | 開發用會員資料 |
| `config/auth.php` | 修改，新增 `member` guard、`members` provider 與 broker |
| `app/Filament/Resources/Members/MemberResource.php` | 後台資源，限制只有列表、檢視、編輯 |
| `app/Filament/Resources/Members/Schemas/MemberForm.php` | 編輯表單 |
| `app/Filament/Resources/Members/Schemas/MemberInfolist.php` | 檢視頁資訊 |
| `app/Filament/Resources/Members/Tables/MembersTable.php` | 列表欄位、搜尋、篩選 |
| `app/Filament/Resources/Members/Actions/MemberActions.php` | 停用、啟用、手動驗證動作，供多處共用 |
| `app/Filament/Resources/Members/RelationManagers/LoginLinksRelationManager.php` | 登入連結紀錄與作廢 |
| `tests/Feature/Member/*.php` | 對應各任務的測試 |

---

### Task 1: Member 模型、資料表與 factory

**Files:**
- Create: `app/Models/Member.php`、`database/factories/MemberFactory.php`、`database/seeders/MemberSeeder.php`
- Create: `database/migrations/*_create_members_table.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Member/MemberModelTest.php`

**Interfaces:**
- Produces: `App\Models\Member`，欄位 `name`、`email`、`email_verified_at`、`password`、`organization`、`is_active`、`remember_token`；`MemberFactory::unverified()`、`MemberFactory::inactive()`。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:model Member -mf --no-interaction
php artisan make:seeder MemberSeeder --no-interaction
php artisan make:test Member/MemberModelTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

`tests/Feature/Member/MemberModelTest.php`：

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_an_active_verified_member(): void
    {
        $member = Member::factory()->create();

        $this->assertTrue($member->is_active);
        $this->assertNotNull($member->email_verified_at);
        $this->assertTrue(Hash::check('password', $member->password));
    }

    public function test_password_is_hashed_and_hidden_from_serialization(): void
    {
        $member = Member::factory()->create(['password' => 'plain-secret']);

        $this->assertNotSame('plain-secret', $member->password);
        $this->assertArrayNotHasKey('password', $member->toArray());
        $this->assertArrayNotHasKey('remember_token', $member->toArray());
    }

    public function test_unverified_and_inactive_states(): void
    {
        $this->assertNull(Member::factory()->unverified()->create()->email_verified_at);
        $this->assertFalse(Member::factory()->inactive()->create()->is_active);
    }

    public function test_email_must_be_unique_among_members(): void
    {
        Member::factory()->create(['email' => 'same@example.com']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Member::factory()->create(['email' => 'same@example.com']);
    }

    public function test_member_email_may_match_an_admin_user_email(): void
    {
        User::factory()->create(['email' => 'shared@example.com']);

        $member = Member::factory()->create(['email' => 'shared@example.com']);

        $this->assertDatabaseHas('members', ['id' => $member->id, 'email' => 'shared@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'shared@example.com']);
    }

    public function test_organization_is_optional(): void
    {
        $member = Member::factory()->create(['organization' => null]);

        $this->assertNull($member->fresh()->organization);
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/MemberModelTest.php`
Expected: FAIL，找不到資料表 `members` 或 factory 缺少欄位。

- [ ] **Step 4: 實作 migration**

編輯 `database/migrations/*_create_members_table.php` 的 `up()` 與 `down()`：

```php
public function up(): void
{
    Schema::create('members', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->string('organization')->nullable();
        $table->boolean('is_active')->default(true)->index();
        $table->rememberToken();
        $table->timestamps();
    });

    Schema::create('member_password_reset_tokens', function (Blueprint $table) {
        $table->string('email')->primary();
        $table->string('token');
        $table->timestamp('created_at')->nullable();
    });
}

public function down(): void
{
    Schema::dropIfExists('member_password_reset_tokens');
    Schema::dropIfExists('members');
}
```

- [ ] **Step 5: 實作模型**

`app/Models/Member.php`：

```php
<?php

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'organization', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class Member extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
```

- [ ] **Step 6: 實作 factory 與 seeder**

`database/factories/MemberFactory.php`：

```php
<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'organization' => fake()->company(),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
```

`database/seeders/MemberSeeder.php` 的 `run()`：

```php
public function run(): void
{
    Member::factory()->count(8)->create();
    Member::factory()->unverified()->count(2)->create();
    Member::factory()->inactive()->count(2)->create();
}
```

並在檔案頂端 `use App\Models\Member;`。在 `DatabaseSeeder::run()` 的 `$this->call([...])` 陣列末端加入 `MemberSeeder::class`。

- [ ] **Step 7: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/MemberModelTest.php`
Expected: PASS，6 個測試。

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Member.php database tests/Feature/Member/MemberModelTest.php
git commit -m "Add Member model, migration, factory and seeder"
```

---

### Task 2: member guard、provider 與 password broker

**Files:**
- Modify: `config/auth.php`
- Test: `tests/Feature/Member/MemberAuthConfigTest.php`

**Interfaces:**
- Consumes: Task 1 的 `Member` 與 `MemberFactory`。
- Produces: guard `member`、provider `members`、password broker `members`，其資料表為 `member_password_reset_tokens`。

- [ ] **Step 1: 產生測試檔**

```bash
php artisan make:test Member/MemberAuthConfigTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class MemberAuthConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_guard_authenticates_members_only(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);

        $this->assertTrue(Auth::guard('member')->attempt(['email' => 'm@example.com', 'password' => 'password']));
        $this->assertTrue(Auth::guard('member')->user()->is($member));
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_member_guard_does_not_authenticate_admin_users(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->assertFalse(Auth::guard('member')->attempt(['email' => 'admin@example.com', 'password' => 'password']));
    }

    public function test_member_password_broker_uses_its_own_token_table(): void
    {
        $this->assertSame('member_password_reset_tokens', config('auth.passwords.members.table'));
        $this->assertSame('members', config('auth.passwords.members.provider'));

        $member = Member::factory()->create();

        $this->assertSame(Password::RESET_LINK_SENT, Password::broker('members')->sendResetLink(['email' => $member->email]));
        $this->assertDatabaseHas('member_password_reset_tokens', ['email' => $member->email]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $member->email]);
    }

    public function test_a_logged_in_member_cannot_reach_the_admin_panel(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')
            ->get('/admin')
            ->assertRedirect();
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/MemberAuthConfigTest.php`
Expected: FAIL，`Auth guard [member] is not defined`。

- [ ] **Step 4: 修改 `config/auth.php`**

頂端加入 `use App\Models\Member;`，並在三個陣列中新增項目，既有項目不動：

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    'member' => [
        'driver' => 'session',
        'provider' => 'members',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => env('AUTH_MODEL', User::class),
    ],

    'members' => [
        'driver' => 'eloquent',
        'model' => Member::class,
    ],
],

'passwords' => [
    'users' => [
        'provider' => 'users',
        'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
        'expire' => 60,
        'throttle' => 60,
    ],

    'members' => [
        'provider' => 'members',
        'table' => 'member_password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/MemberAuthConfigTest.php`
Expected: PASS，4 個測試。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/auth.php tests/Feature/Member/MemberAuthConfigTest.php
git commit -m "Add member guard, provider and password broker"
```

---

### Task 3: MemberLoginLink 模型與資料表

**Files:**
- Create: `app/Models/MemberLoginLink.php`、`database/factories/MemberLoginLinkFactory.php`
- Create: `database/migrations/*_create_member_login_links_table.php`
- Modify: `app/Models/Member.php`
- Test: `tests/Feature/Member/MemberLoginLinkTest.php`

**Interfaces:**
- Consumes: `Member`。
- Produces:
  - `MemberLoginLink` 常數 `STATUS_USABLE = 'usable'`、`STATUS_USED = 'used'`、`STATUS_EXPIRED = 'expired'`、`STATUS_REVOKED = 'revoked'`。
  - `MemberLoginLink::status(): string`、`isUsable(): bool`、`revoke(): bool`，作廢成功回傳 true，不可作廢回傳 false 且不改動資料。
  - `Member::loginLinks(): HasMany`、`MemberLoginLink::member(): BelongsTo`。
  - `MemberLoginLinkFactory::used()`、`expired()`、`revoked()`。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:model MemberLoginLink -mf --no-interaction
php artisan make:test Member/MemberLoginLinkTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberLoginLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_link_is_usable(): void
    {
        $link = MemberLoginLink::factory()->create();

        $this->assertSame(MemberLoginLink::STATUS_USABLE, $link->status());
        $this->assertTrue($link->isUsable());
    }

    public function test_status_for_used_expired_and_revoked_links(): void
    {
        $this->assertSame(MemberLoginLink::STATUS_USED, MemberLoginLink::factory()->used()->create()->status());
        $this->assertSame(MemberLoginLink::STATUS_EXPIRED, MemberLoginLink::factory()->expired()->create()->status());
        $this->assertSame(MemberLoginLink::STATUS_REVOKED, MemberLoginLink::factory()->revoked()->create()->status());
    }

    public function test_a_link_expiring_exactly_now_is_expired(): void
    {
        $this->freezeTime();

        $link = MemberLoginLink::factory()->create(['expires_at' => now()]);

        $this->assertSame(MemberLoginLink::STATUS_EXPIRED, $link->status());
    }

    public function test_used_takes_precedence_over_expired(): void
    {
        $link = MemberLoginLink::factory()->used()->create(['expires_at' => now()->subDay()]);

        $this->assertSame(MemberLoginLink::STATUS_USED, $link->status());
    }

    public function test_revoke_marks_a_usable_link(): void
    {
        $link = MemberLoginLink::factory()->create();

        $this->assertTrue($link->revoke());
        $this->assertNotNull($link->fresh()->revoked_at);
        $this->assertSame(MemberLoginLink::STATUS_REVOKED, $link->fresh()->status());
    }

    public function test_revoke_does_nothing_for_used_or_expired_links(): void
    {
        $used = MemberLoginLink::factory()->used()->create();
        $expired = MemberLoginLink::factory()->expired()->create();

        $this->assertFalse($used->revoke());
        $this->assertFalse($expired->revoke());
        $this->assertNull($used->fresh()->revoked_at);
        $this->assertNull($expired->fresh()->revoked_at);
    }

    public function test_deleting_a_member_deletes_its_links(): void
    {
        $member = Member::factory()->create();
        MemberLoginLink::factory()->count(2)->for($member)->create();

        $member->delete();

        $this->assertDatabaseCount('member_login_links', 0);
    }

    public function test_member_has_login_links_relation(): void
    {
        $member = Member::factory()->create();
        MemberLoginLink::factory()->count(3)->for($member)->create();

        $this->assertCount(3, $member->loginLinks);
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/MemberLoginLinkTest.php`
Expected: FAIL，找不到資料表或類別。

- [ ] **Step 4: 實作 migration**

```php
public function up(): void
{
    Schema::create('member_login_links', function (Blueprint $table) {
        $table->id();
        $table->foreignId('member_id')->constrained()->cascadeOnDelete();
        $table->string('token_hash')->unique();
        $table->timestamp('expires_at');
        $table->timestamp('used_at')->nullable();
        $table->timestamp('revoked_at')->nullable();
        $table->timestamp('created_at')->useCurrent();
    });
}

public function down(): void
{
    Schema::dropIfExists('member_login_links');
}
```

- [ ] **Step 5: 實作模型**

`app/Models/MemberLoginLink.php`：

```php
<?php

namespace App\Models;

use Database\Factories\MemberLoginLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'token_hash', 'expires_at', 'used_at', 'revoked_at'])]
class MemberLoginLink extends Model
{
    /** @use HasFactory<MemberLoginLinkFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const STATUS_USABLE = 'usable';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function status(): string
    {
        return match (true) {
            $this->used_at !== null => self::STATUS_USED,
            $this->revoked_at !== null => self::STATUS_REVOKED,
            $this->expires_at->lte(now()) => self::STATUS_EXPIRED,
            default => self::STATUS_USABLE,
        };
    }

    public function isUsable(): bool
    {
        return $this->status() === self::STATUS_USABLE;
    }

    public function revoke(): bool
    {
        if (! $this->isUsable()) {
            return false;
        }

        $this->forceFill(['revoked_at' => now()])->save();

        return true;
    }
}
```

在 `app/Models/Member.php` 加入關聯，並 `use Illuminate\Database\Eloquent\Relations\HasMany;`：

```php
public function loginLinks(): HasMany
{
    return $this->hasMany(MemberLoginLink::class);
}
```

- [ ] **Step 6: 實作 factory**

`database/factories/MemberLoginLinkFactory.php`：

```php
<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MemberLoginLink>
 */
class MemberLoginLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => ['used_at' => now()->subMinute()]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => ['revoked_at' => now()->subMinute()]);
    }
}
```

- [ ] **Step 7: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/MemberLoginLinkTest.php`
Expected: PASS，8 個測試。

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models database tests/Feature/Member/MemberLoginLinkTest.php
git commit -m "Add MemberLoginLink model with status and revoke"
```

---

### Task 4: quiz_attempts 外鍵改指向 members

**Files:**
- Create: `database/migrations/*_point_quiz_attempts_user_id_to_members.php`
- Modify: `app/Models/QuizAttempt.php`
- Test: `tests/Feature/Member/QuizAttemptMemberTest.php`

**Interfaces:**
- Consumes: `Member`。
- Produces: `QuizAttempt::member(): BelongsTo`，外鍵欄位 `user_id`。

- [ ] **Step 1: 確認本機資料庫沒有需要搬移的資料**

使用 Boost 的 `database-query` 工具執行：

```sql
select count(*) from quiz_attempts where user_id is not null
```

Expected: 0。若不為 0，停止並回報，不要繼續，因為舊的使用者 id 無法對應到會員。

- [ ] **Step 2: 產生檔案**

```bash
php artisan make:migration point_quiz_attempts_user_id_to_members --no-interaction
php artisan make:test Member/QuizAttemptMemberTest --phpunit --no-interaction
```

- [ ] **Step 3: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\PersonalityType;
use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuizAttemptMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_id_foreign_key_points_to_members(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('quiz_attempts'))
            ->filter(fn (array $key) => $key['columns'] === ['user_id']);

        $this->assertCount(1, $foreignKeys);
        $this->assertSame('members', $foreignKeys->first()['foreign_table']);
    }

    public function test_quiz_attempt_has_member_relation_using_user_id(): void
    {
        $relation = (new QuizAttempt)->member();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('user_id', $relation->getForeignKeyName());
    }

    public function test_deleting_a_member_keeps_quiz_attempts_and_nulls_user_id(): void
    {
        PersonalityType::query()->create([
            'code' => 'SNHG',
            'name' => '測試類型',
            'summary' => '摘要',
            'description' => '說明',
            'strengths' => [],
            'weaknesses' => [],
        ]);
        $member = Member::factory()->create();
        $attempt = QuizAttempt::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $member->id,
            'scores' => [],
            'type_code' => 'SNHG',
            'completed_at' => now(),
        ]);

        $this->assertTrue($attempt->member->is($member));

        $member->delete();

        $this->assertNull($attempt->fresh()->user_id);
    }
}
```

若 `personality_types` 還有其他非空欄位導致建立失敗，依 `database/migrations/2026_08_22_002256_create_personality_types_table.php` 補齊，不要放寬資料表限制。

- [ ] **Step 4: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/QuizAttemptMemberTest.php`
Expected: FAIL，外鍵仍指向 `users`，或 `member()` 不存在。

- [ ] **Step 5: 實作 migration**

```php
public function up(): void
{
    Schema::table('quiz_attempts', function (Blueprint $table) {
        $table->dropForeign(['user_id']);
    });

    Schema::table('quiz_attempts', function (Blueprint $table) {
        $table->foreign('user_id')->references('id')->on('members')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('quiz_attempts', function (Blueprint $table) {
        $table->dropForeign(['user_id']);
    });

    Schema::table('quiz_attempts', function (Blueprint $table) {
        $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
    });
}
```

- [ ] **Step 6: 修改 `QuizAttempt`**

在 `personalityType()` 後加入：

```php
public function member(): BelongsTo
{
    return $this->belongsTo(Member::class, 'user_id');
}
```

- [ ] **Step 7: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/QuizAttemptMemberTest.php`
Expected: PASS，3 個測試。

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/QuizAttempt.php database/migrations tests/Feature/Member/QuizAttemptMemberTest.php
git commit -m "Point quiz_attempts.user_id foreign key to members"
```

---

### Task 5: MemberResource 列表、檢視與編輯

**Files:**
- Create: `app/Filament/Resources/Members/MemberResource.php` 與其 `Pages/`、`Schemas/`、`Tables/`
- Test: `tests/Feature/Member/MemberResourceTest.php`

**Interfaces:**
- Consumes: `Member`。
- Produces: `MemberResource`，頁面 `ListMembers`、`ViewMember`、`EditMember`，命名空間 `App\Filament\Resources\Members\Pages`；`MemberResource::getRelations()` 於 Task 7 填入。

- [ ] **Step 1: 產生資源**

```bash
php artisan make:filament-resource Member --view --no-interaction
ls -R app/Filament/Resources/Members
```

Expected：看到 `MemberResource.php`、`Pages/ListMembers.php`、`CreateMember.php`、`ViewMember.php`、`EditMember.php`，以及 `Schemas/`、`Tables/`。若檔案位置或命名與下方不同，以實際產生的命名空間為準，並同步調整本計畫後續的 `use` 與測試。刪除不需要的建立頁：

```bash
rm app/Filament/Resources/Members/Pages/CreateMember.php
```

- [ ] **Step 2: 寫失敗的測試**

`php artisan make:test Member/MemberResourceTest --phpunit --no-interaction`，內容：

```php
<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_shows_members(): void
    {
        $members = Member::factory()->count(3)->create();

        Livewire::test(ListMembers::class)
            ->assertOk()
            ->assertCanSeeTableRecords($members);
    }

    public function test_list_can_search_by_name_email_and_organization(): void
    {
        $alice = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com', 'organization' => 'Kotlin Taiwan']);
        $bob = Member::factory()->create(['name' => 'Bob Lin', 'email' => 'bob@example.com', 'organization' => 'WordPress Taiwan']);

        Livewire::test(ListMembers::class)
            ->searchTable('Alice')
            ->assertCanSeeTableRecords([$alice])
            ->assertCanNotSeeTableRecords([$bob])
            ->searchTable('bob@example.com')
            ->assertCanSeeTableRecords([$bob])
            ->assertCanNotSeeTableRecords([$alice])
            ->searchTable('Kotlin')
            ->assertCanSeeTableRecords([$alice])
            ->assertCanNotSeeTableRecords([$bob]);
    }

    public function test_list_can_filter_by_active_status(): void
    {
        $active = Member::factory()->create();
        $inactive = Member::factory()->inactive()->create();

        Livewire::test(ListMembers::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive])
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_list_can_filter_by_email_verification(): void
    {
        $verified = Member::factory()->create();
        $unverified = Member::factory()->unverified()->create();

        Livewire::test(ListMembers::class)
            ->filterTable('email_verified_at', true)
            ->assertCanSeeTableRecords([$verified])
            ->assertCanNotSeeTableRecords([$unverified])
            ->filterTable('email_verified_at', false)
            ->assertCanSeeTableRecords([$unverified])
            ->assertCanNotSeeTableRecords([$verified]);
    }

    public function test_members_without_organization_render_in_list_and_view(): void
    {
        $member = Member::factory()->create(['organization' => null]);

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$member])
            ->searchTable($member->name)
            ->assertCanSeeTableRecords([$member]);

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])->assertOk();
    }

    public function test_edit_saves_name_organization_and_active_status(): void
    {
        $member = Member::factory()->create(['organization' => 'Old Org']);

        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->fillForm(['name' => '新名字', 'organization' => 'New Org', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'name' => '新名字',
            'organization' => 'New Org',
            'is_active' => false,
        ]);
    }

    public function test_edit_cannot_change_email_or_password(): void
    {
        $member = Member::factory()->create(['email' => 'keep@example.com']);
        $originalHash = $member->password;

        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->assertFormFieldDoesNotExist('password')
            ->fillForm(['name' => '改名', 'email' => 'changed@example.com'])
            ->call('save');

        $member->refresh();

        $this->assertSame('keep@example.com', $member->email);
        $this->assertSame($originalHash, $member->password);
    }

    public function test_edit_requires_a_name(): void
    {
        $member = Member::factory()->create();

        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->fillForm(['name' => ''])
            ->call('save')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_resource_has_no_create_page_and_no_delete(): void
    {
        $member = Member::factory()->create();

        $this->assertSame(['index', 'view', 'edit'], array_keys(MemberResource::getPages()));
        $this->assertFalse(MemberResource::canCreate());
        $this->assertFalse(MemberResource::canDelete($member));
        $this->assertFalse(MemberResource::canDeleteAny());
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/MemberResourceTest.php`
Expected: FAIL，欄位、篩選或 `canCreate` 與預期不符。

- [ ] **Step 4: 實作 `MemberResource`**

完整取代 `app/Filament/Resources/Members/MemberResource.php`：

```php
<?php

namespace App\Filament\Resources\Members;

use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Filament\Resources\Members\Schemas\MemberInfolist;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\Member;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = '會員';

    protected static ?string $pluralModelLabel = '會員';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MemberInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'view' => ViewMember::route('/{record}'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
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

- [ ] **Step 5: 實作編輯表單**

`app/Filament/Resources/Members/Schemas/MemberForm.php`：

```php
<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('姓名')
                ->required()
                ->maxLength(255),
            TextInput::make('organization')
                ->label('所屬社群或公司')
                ->maxLength(255),
            Toggle::make('is_active')
                ->label('啟用'),
            TextInput::make('email')
                ->label('Email')
                ->disabled()
                ->dehydrated(false),
            DateTimePicker::make('email_verified_at')
                ->label('Email 驗證時間')
                ->placeholder('尚未驗證')
                ->disabled()
                ->dehydrated(false),
            DateTimePicker::make('created_at')
                ->label('註冊時間')
                ->disabled()
                ->dehydrated(false),
        ]);
    }
}
```

- [ ] **Step 6: 實作檢視頁資訊**

`app/Filament/Resources/Members/Schemas/MemberInfolist.php`：

```php
<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('姓名'),
            TextEntry::make('email')->label('Email'),
            TextEntry::make('organization')
                ->label('所屬社群或公司')
                ->placeholder('未填寫'),
            IconEntry::make('is_active')
                ->label('啟用')
                ->boolean(),
            TextEntry::make('email_verified_at')
                ->label('Email 驗證時間')
                ->dateTime()
                ->placeholder('尚未驗證'),
            TextEntry::make('created_at')
                ->label('註冊時間')
                ->dateTime(),
        ]);
    }
}
```

- [ ] **Step 7: 實作列表**

`app/Filament/Resources/Members/Tables/MembersTable.php`：

```php
<?php

namespace App\Filament\Resources\Members\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('姓名')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('organization')
                    ->label('所屬社群或公司')
                    ->searchable()
                    ->placeholder('未填寫'),
                IconColumn::make('is_active')
                    ->label('啟用')
                    ->boolean(),
                IconColumn::make('email_verified_at')
                    ->label('Email 驗證')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('註冊時間')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('啟用狀態')
                    ->trueLabel('啟用')
                    ->falseLabel('停用'),
                TernaryFilter::make('email_verified_at')
                    ->label('Email 驗證')
                    ->nullable()
                    ->trueLabel('已驗證')
                    ->falseLabel('未驗證'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
```

- [ ] **Step 8: 調整頁面類別**

確認 `Pages/ListMembers.php` 的 `getHeaderActions()` 不含 `CreateAction`，`Pages/EditMember.php` 的 `getHeaderActions()` 不含 `DeleteAction`，兩者皆回傳 `[]`。`Pages/ViewMember.php` 保留 `EditAction::make()` 即可。

- [ ] **Step 9: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/MemberResourceTest.php`
Expected: PASS，9 個測試。

若 `filterTable('email_verified_at', ...)` 與 `nullable()` 行為不符，先用 `search-docs` 查詢 Filament 的 TernaryFilter nullable 說明，不要改成自訂 query 以外的寫法繞過。

- [ ] **Step 10: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament tests/Feature/Member/MemberResourceTest.php
git commit -m "Add Filament MemberResource with list, view and edit"
```

---

### Task 6: 停用、啟用與手動驗證動作

**Files:**
- Create: `app/Filament/Resources/Members/Actions/MemberActions.php`
- Modify: `app/Filament/Resources/Members/Tables/MembersTable.php`、`Pages/ViewMember.php`、`Pages/EditMember.php`
- Test: `tests/Feature/Member/MemberActionsTest.php`

**Interfaces:**
- Consumes: `MemberResource` 與頁面。
- Produces: `MemberActions::deactivate()`、`activate()`、`markVerified()`，各自回傳 `Filament\Actions\Action`，動作名稱分別是 `deactivate`、`activate`、`markVerified`。

- [ ] **Step 1: 寫失敗的測試**

`php artisan make:test Member/MemberActionsTest --phpunit --no-interaction`：

```php
<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_deactivate_action_disables_an_active_member(): void
    {
        $member = Member::factory()->create();

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('deactivate')->table($member))
            ->assertNotified();

        $this->assertFalse($member->fresh()->is_active);
    }

    public function test_activate_action_enables_an_inactive_member(): void
    {
        $member = Member::factory()->inactive()->create();

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('activate')->table($member))
            ->assertNotified();

        $this->assertTrue($member->fresh()->is_active);
    }

    public function test_mark_verified_sets_verification_time(): void
    {
        $member = Member::factory()->unverified()->create();

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('markVerified')->table($member))
            ->assertNotified();

        $this->assertNotNull($member->fresh()->email_verified_at);
    }

    public function test_actions_are_only_visible_when_applicable(): void
    {
        $active = Member::factory()->create();
        $inactive = Member::factory()->inactive()->unverified()->create();

        Livewire::test(ListMembers::class)
            ->assertActionVisible(TestAction::make('deactivate')->table($active))
            ->assertActionHidden(TestAction::make('activate')->table($active))
            ->assertActionHidden(TestAction::make('markVerified')->table($active))
            ->assertActionHidden(TestAction::make('deactivate')->table($inactive))
            ->assertActionVisible(TestAction::make('activate')->table($inactive))
            ->assertActionVisible(TestAction::make('markVerified')->table($inactive));
    }

    public function test_view_page_exposes_the_same_actions(): void
    {
        $member = Member::factory()->create();

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction('deactivate')
            ->assertNotified();

        $this->assertFalse($member->fresh()->is_active);
    }
}
```

- [ ] **Step 2: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/MemberActionsTest.php`
Expected: FAIL，找不到動作 `deactivate`。

- [ ] **Step 3: 實作動作類別**

`app/Filament/Resources/Members/Actions/MemberActions.php`：

```php
<?php

namespace App\Filament\Resources\Members\Actions;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class MemberActions
{
    public static function deactivate(): Action
    {
        return Action::make('deactivate')
            ->label('停用')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('停用會員')
            ->modalDescription('停用後，此會員將無法登入官網。')
            ->visible(fn (Member $record): bool => $record->is_active)
            ->action(function (Member $record): void {
                $record->update(['is_active' => false]);

                Notification::make()->title('已停用會員')->success()->send();
            });
    }

    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('啟用')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Member $record): bool => ! $record->is_active)
            ->action(function (Member $record): void {
                $record->update(['is_active' => true]);

                Notification::make()->title('已啟用會員')->success()->send();
            });
    }

    public static function markVerified(): Action
    {
        return Action::make('markVerified')
            ->label('標記為已驗證')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->requiresConfirmation()
            ->visible(fn (Member $record): bool => $record->email_verified_at === null)
            ->action(function (Member $record): void {
                $record->forceFill(['email_verified_at' => now()])->save();

                Notification::make()->title('已標記為已驗證')->success()->send();
            });
    }
}
```

- [ ] **Step 4: 掛到列表與頁面**

在 `MembersTable::configure()` 的 `recordActions` 改為：

```php
->recordActions([
    ViewAction::make(),
    EditAction::make(),
    MemberActions::deactivate(),
    MemberActions::activate(),
    MemberActions::markVerified(),
])
```

並加上 `use App\Filament\Resources\Members\Actions\MemberActions;`。

`Pages/ViewMember.php` 與 `Pages/EditMember.php` 的 `getHeaderActions()` 回傳：

```php
return [
    MemberActions::deactivate(),
    MemberActions::activate(),
    MemberActions::markVerified(),
];
```

`ViewMember` 另外保留 `EditAction::make()` 在陣列最前面。兩個檔案都加上 `use App\Filament\Resources\Members\Actions\MemberActions;`。

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/MemberActionsTest.php`
Expected: PASS，5 個測試。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament tests/Feature/Member/MemberActionsTest.php
git commit -m "Add activate, deactivate and mark-verified member actions"
```

---

### Task 7: 登入連結紀錄的檢視與作廢

**Files:**
- Create: `app/Filament/Resources/Members/RelationManagers/LoginLinksRelationManager.php`
- Modify: `app/Filament/Resources/Members/MemberResource.php`
- Test: `tests/Feature/Member/LoginLinksRelationManagerTest.php`

**Interfaces:**
- Consumes: `Member::loginLinks()`、`MemberLoginLink::status()`、`MemberLoginLink::revoke()`。
- Produces: `LoginLinksRelationManager`，列表動作名稱 `revoke`。

- [ ] **Step 1: 寫失敗的測試**

`php artisan make:test Member/LoginLinksRelationManagerTest --phpunit --no-interaction`：

```php
<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\LoginLinksRelationManager;
use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginLinksRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_relation_manager_renders_on_view_and_edit_pages(): void
    {
        $member = Member::factory()->create();

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->assertSeeLivewire(LoginLinksRelationManager::class);

        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->assertSeeLivewire(LoginLinksRelationManager::class);
    }

    public function test_lists_only_the_members_own_links(): void
    {
        $member = Member::factory()->create();
        $own = MemberLoginLink::factory()->count(2)->for($member)->create();
        $other = MemberLoginLink::factory()->create();

        Livewire::test(LoginLinksRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->assertCanSeeTableRecords($own)
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_token_hash_is_never_rendered(): void
    {
        $member = Member::factory()->create();
        $link = MemberLoginLink::factory()->for($member)->create();

        Livewire::test(LoginLinksRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->assertDontSee($link->token_hash);
    }

    public function test_revoke_action_revokes_a_usable_link(): void
    {
        $member = Member::factory()->create();
        $link = MemberLoginLink::factory()->for($member)->create();

        Livewire::test(LoginLinksRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->callAction(TestAction::make('revoke')->table($link))
            ->assertNotified();

        $this->assertSame(MemberLoginLink::STATUS_REVOKED, $link->fresh()->status());
    }

    public function test_revoke_is_hidden_for_used_expired_and_revoked_links(): void
    {
        $member = Member::factory()->create();
        $used = MemberLoginLink::factory()->used()->for($member)->create();
        $expired = MemberLoginLink::factory()->expired()->for($member)->create();
        $revoked = MemberLoginLink::factory()->revoked()->for($member)->create();

        Livewire::test(LoginLinksRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])
            ->assertActionHidden(TestAction::make('revoke')->table($used))
            ->assertActionHidden(TestAction::make('revoke')->table($expired))
            ->assertActionHidden(TestAction::make('revoke')->table($revoked));
    }
}
```

- [ ] **Step 2: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/LoginLinksRelationManagerTest.php`
Expected: FAIL，找不到 `LoginLinksRelationManager`。

- [ ] **Step 3: 實作關聯管理器**

```bash
php artisan make:filament-relation-manager MemberResource loginLinks id --no-interaction
```

若上述指令的引數順序與目前版本不同，先用 `php artisan make:filament-relation-manager --help` 確認，再以 `Members\MemberResource`、關聯 `loginLinks`、標題屬性 `id` 產生。產生後完整取代內容：

```php
<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Models\MemberLoginLink;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoginLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'loginLinks';

    protected static ?string $title = '登入連結';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('到期時間')
                    ->dateTime(),
                TextColumn::make('used_at')
                    ->label('使用時間')
                    ->dateTime()
                    ->placeholder('尚未使用'),
                TextColumn::make('status')
                    ->label('狀態')
                    ->state(fn (MemberLoginLink $record): string => match ($record->status()) {
                        MemberLoginLink::STATUS_USED => '已使用',
                        MemberLoginLink::STATUS_EXPIRED => '已過期',
                        MemberLoginLink::STATUS_REVOKED => '已作廢',
                        default => '可使用',
                    })
                    ->badge()
                    ->color(fn (MemberLoginLink $record): string => match ($record->status()) {
                        MemberLoginLink::STATUS_USABLE => 'success',
                        MemberLoginLink::STATUS_USED => 'gray',
                        MemberLoginLink::STATUS_EXPIRED => 'warning',
                        MemberLoginLink::STATUS_REVOKED => 'danger',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('revoke')
                    ->label('作廢')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('作廢登入連結')
                    ->modalDescription('作廢後，此連結將無法再用於登入。')
                    ->visible(fn (MemberLoginLink $record): bool => $record->isUsable())
                    ->action(function (MemberLoginLink $record): void {
                        if ($record->revoke()) {
                            Notification::make()->title('已作廢登入連結')->success()->send();
                        }
                    }),
            ]);
    }
}
```

- [ ] **Step 4: 註冊到資源**

在 `MemberResource::getRelations()` 回傳：

```php
return [
    LoginLinksRelationManager::class,
];
```

並加上 `use App\Filament\Resources\Members\RelationManagers\LoginLinksRelationManager;`。

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/LoginLinksRelationManagerTest.php`
Expected: PASS，5 個測試。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament tests/Feature/Member/LoginLinksRelationManagerTest.php
git commit -m "Add login links relation manager with revoke action"
```

---

### Task 8: 整體驗證

**Files:** 無新增。

- [ ] **Step 1: 執行所有會員相關測試**

Run: `php artisan test --compact tests/Feature/Member`
Expected: PASS，共 40 個測試。

- [ ] **Step 2: 詢問使用者是否執行完整測試套件**

依專案規則，相關測試通過後詢問使用者是否要跑整個測試套件。使用者同意後執行 `php artisan test --compact`，Expected: 全部 PASS。

- [ ] **Step 3: 手動確認後台**

```bash
php artisan migrate --no-interaction
php artisan db:seed --class=MemberSeeder --no-interaction
```

用瀏覽器開啟 `http://localhost:8000/admin`，以後台管理者登入，確認：

- 側邊欄出現「會員」，列表顯示 12 位會員，搜尋與兩個篩選可用。
- 沒有「新增會員」按鈕，編輯頁沒有刪除按鈕與密碼欄位。
- 停用、啟用與標記為已驗證在對應狀態下才出現。
- 檢視與編輯頁下方有「登入連結」區塊，目前為空。

- [ ] **Step 4: 回報**

回報階段一完成，並提醒階段二另開實作計畫。附註：`User` 目前未實作 `FilamentUser`，在非 `local` 環境後台會拒絕所有人登入，此為既有狀況，不在本計畫範圍。

---

## 不在本計畫內

- 前台註冊、登入、登出、Email 驗證、忘記與重設密碼、登入連結的建立與使用，以及後台的「寄送登入連結」動作，屬於階段二。
- 過期與已使用的登入連結保留 30 天後清除的排程，屬於階段二，隨連結的建立流程一併實作。
- 會員的 rate limiting 與停用會員即時登出的 middleware，屬於階段二。
