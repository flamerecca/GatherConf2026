# 免密碼註冊與稍後設定密碼實作計畫

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 讓訪客只填姓名與 Email 就能註冊，註冊後透過信箱中的登入連結登入並驗證，之後可以在會員中心設定密碼，沒有密碼的帳號不能用密碼登入。

**Architecture:** `members.password` 改為可為空。註冊與「要求登入連結」共用 `MemberController` 上的兩個輔助方法，因此限流與寄送邏輯只有一份。註冊不建立 session、不接受密碼。新增一個設定密碼頁，是否需要目前密碼取決於會員是否已有密碼，以及 session 中記錄的最近一次連結登入時間。

**Tech Stack:** PHP 8.5、Laravel 13、Filament 4.12、PHPUnit 12、SQLite。

**Spec:** `docs/superpowers/specs/2026-10-15-passwordless-registration-design.md`，實作其全部內容，單一階段。

## Global Constraints

- 不新增任何 composer 依賴，不建立新的 `app` 底下的基礎資料夾。
- 模型使用 `#[Fillable([...])]` 與 `#[Hidden([...])]` 屬性，見 `.ai/rules/models.md`。
- 不修改既有的建立資料表 migration，因為 `members` 已在正式環境建立，以新增 migration 變更。
- 所有檔案以 `php artisan make:` 指令建立，並傳入 `--no-interaction`。
- 所有測試使用 PHPUnit 類別，使用 `RefreshDatabase`，建立資料一律用 factory，前台頁面測試在 `setUp()` 呼叫 `$this->withoutVite()`。
- 註冊絕不接受密碼、絕不建立 session、絕不洩漏 Email 是否已註冊。
- 沒有密碼的帳號，密碼登入的失敗訊息與密碼錯誤完全相同。
- 登入連結 15 分鐘有效，單次使用，每個 Email 每小時最多 5 次，註冊與要求登入連結共用同一個計數。
- 前台文案放入 `lang/zh_TW/member.php` 與 `lang/en/member.php`，台灣用語，中英文之間留半形空格，中文句中不使用括號補充說明。
- 每次修改 PHP 檔案後，在 commit 前執行 `vendor/bin/pint --dirty --format agent`。
- 每次更新測試後執行該單一測試，不先跑整個測試套件。

## Review Focus

1. 預先劫持：以受害者的 Email 註冊後，攻擊者沒有任何憑證、session 或密碼可以在受害者驗證後使用，Task 2 測試。
2. 註冊對已註冊、未註冊與已停用的 Email 回應一致，且不覆寫既有會員的姓名與社群，Task 2 測試。
3. 沒有密碼的帳號不能用密碼登入，也不會拋出例外，Task 1 測試。
4. 已有密碼者變更密碼：錯誤的目前密碼被拒絕，超過 15 分鐘的連結登入不再免目前密碼，Task 4 測試。
5. 註冊與登入連結的每小時額度確實共用，且並發註冊同一個 Email 不會產生兩筆，Task 2 測試。
6. 重設密碼會驗證 Email，但不會讓已停用的帳號變成可登入，Task 3 測試。

---

## File Structure

| 檔案 | 責任 |
| :---- | :---- |
| `database/migrations/*_make_members_password_nullable.php` | `members.password` 可為空 |
| `app/Models/Member.php`、`database/factories/MemberFactory.php` | `hasPassword()` 與 `passwordless()` |
| `app/Http/Controllers/Member/MemberController.php` | 共用的 `takeLinkQuota()` 與 `sendLoginLink()` |
| `app/Http/Controllers/Member/RegisterController.php` | 免密碼註冊與結果頁 |
| `app/Http/Controllers/Member/LoginController.php`、`LoginLinkController.php`、`PasswordResetController.php`、`CenterController.php` | 修改 |
| `app/Http/Controllers/Member/PasswordController.php` | 設定與變更密碼 |
| `app/Providers/AppServiceProvider.php`、`routes/web.php` | 限流與路由 |
| `resources/views/member/register.blade.php`、`register-sent.blade.php`、`password.blade.php`、`login.blade.php`、`center.blade.php` | 頁面 |
| `lang/zh_TW/member.php`、`lang/en/member.php` | 文案 |
| `app/Filament/Resources/Members/Schemas/MemberInfolist.php`、`Tables/MembersTable.php` | 唯讀的密碼指示 |
| `tests/Feature/Member/Passwordless*.php` | 對應各任務的測試 |

---

### Task 1: 資料層與密碼登入的安全處理

**Files:**
- Create: `database/migrations/*_make_members_password_nullable.php`
- Modify: `app/Models/Member.php`、`database/factories/MemberFactory.php`、`app/Http/Controllers/Member/LoginController.php`
- Test: `tests/Feature/Member/PasswordlessDataTest.php`

**Interfaces:**
- Produces: `Member::hasPassword(): bool`；`MemberFactory::passwordless()`；`members.password` 可為空；沒有密碼的會員密碼登入回傳與密碼錯誤相同的驗證訊息。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:migration make_members_password_nullable --no-interaction
php artisan make:test Member/PasswordlessDataTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordlessDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_password_column_is_nullable(): void
    {
        $column = collect(Schema::getColumns('members'))->firstWhere('name', 'password');

        $this->assertTrue($column['nullable']);
    }

    public function test_has_password_reflects_whether_a_hash_is_stored(): void
    {
        $this->assertTrue(Member::factory()->create()->hasPassword());
        $this->assertFalse(Member::factory()->passwordless()->create()->hasPassword());
    }

    public function test_a_passwordless_member_cannot_log_in_with_any_password(): void
    {
        Member::factory()->passwordless()->create(['email' => 'new@example.com']);

        foreach (['', 'password', 'null', ' '] as $guess) {
            $this->post('/login', ['email' => 'new@example.com', 'password' => $guess === '' ? 'x' : $guess])
                ->assertSessionHasErrors(['email' => __('member.errors.failed')]);
        }

        $this->assertGuest('member');
    }

    public function test_the_failure_message_is_the_same_for_passwordless_wrong_password_and_unknown(): void
    {
        Member::factory()->passwordless()->create(['email' => 'none@example.com']);
        Member::factory()->create(['email' => 'has@example.com']);

        $messages = collect([
            ['none@example.com', 'whatever'],
            ['has@example.com', 'wrong-password'],
            ['ghost@example.com', 'whatever'],
        ])->map(function (array $credentials): string {
            return (string) $this->post('/login', ['email' => $credentials[0], 'password' => $credentials[1]])
                ->assertSessionHasErrors('email')
                ->getSession()->get('errors')->first('email');
        });

        $this->assertCount(1, $messages->unique());
    }

    public function test_a_deactivated_passwordless_member_gets_the_generic_failure(): void
    {
        Member::factory()->passwordless()->inactive()->create(['email' => 'off@example.com']);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'anything'])
            ->assertSessionHasErrors(['email' => __('member.errors.failed')]);
    }

    public function test_the_admin_user_password_column_is_untouched(): void
    {
        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'password');

        $this->assertFalse($column['nullable']);
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/PasswordlessDataTest.php`
Expected: FAIL，`passwordless()` 與 `hasPassword()` 不存在。

- [ ] **Step 4: 實作**

migration：

```php
public function up(): void
{
    Schema::table('members', function (Blueprint $table) {
        $table->string('password')->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('members', function (Blueprint $table) {
        $table->string('password')->nullable(false)->change();
    });
}
```

`down()` 在存在無密碼會員時會失敗，這是預期行為，不處理。

`Member` 在 `friendUrl()` 之前加入：

```php
public function hasPassword(): bool
{
    return $this->password !== null && $this->password !== '';
}
```

`MemberFactory` 加入：

```php
public function passwordless(): static
{
    return $this->state(fn (array $attributes) => ['password' => null]);
}
```

`LoginController::store` 的比對條件改為：

```php
if ($member === null || ! $member->hasPassword() || ! Hash::check($credentials['password'], $member->password)) {
    throw ValidationException::withMessages(['email' => __('member.errors.failed')]);
}
```

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/PasswordlessDataTest.php tests/Feature/Member`
Expected: PASS。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app database tests/Feature/Member/PasswordlessDataTest.php
git commit -m "Allow members without a password and reject password login for them"
```

---

### Task 2: 免密碼註冊

**Files:**
- Modify: `app/Http/Controllers/Member/MemberController.php`、`RegisterController.php`、`LoginLinkController.php`、`routes/web.php`、`resources/views/member/register.blade.php`、`lang/zh_TW/member.php`、`lang/en/member.php`、`tests/Feature/Member/RegisterTest.php`、`tests/Feature/Member/MemberHardeningTest.php`
- Create: `resources/views/member/register-sent.blade.php`
- Test: `tests/Feature/Member/PasswordlessRegistrationTest.php`

**Interfaces:**
- Consumes: Task 1 的 `Member`，階段二的 `MemberLoginLink::issueFor`、`throttleKey`、`HOURLY_LIMIT`、`MemberLoginLinkNotification`。
- Produces:
  - `MemberController::takeLinkQuota(string $email): void`，超過上限回應 429，否則計次。
  - `MemberController::sendLoginLink(Member $member): void`，建立連結並安靜寄出。
  - `POST /register` 不再建立 session，導向 `member.register.sent`；`GET /register/sent`，名稱 `member.register.sent[.en]`。

- [ ] **Step 1: 產生測試檔**

```bash
php artisan make:test Member/PasswordlessRegistrationTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Notifications\MemberLoginLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordlessRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function register(array $overrides = [], string $path = '/register'): \Illuminate\Testing\TestResponse
    {
        return $this->post($path, array_merge([
            'name' => '王小明',
            'email' => 'new@example.com',
            'organization' => 'Kotlin Taiwan',
        ], $overrides));
    }

    public function test_the_form_has_no_password_fields(): void
    {
        $this->get('/register')->assertOk()
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="password_confirmation"', false)
            ->assertSee('name="email"', false);
    }

    public function test_registering_creates_an_unverified_member_without_a_password_and_without_a_session(): void
    {
        Notification::fake();

        $this->register()->assertRedirect('/register/sent');

        $member = Member::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertNull($member->password);
        $this->assertNull($member->email_verified_at);
        $this->assertTrue($member->is_active);
        $this->assertSame('Kotlin Taiwan', $member->organization);
        $this->assertGuest('member');
        Notification::assertSentTo($member, MemberLoginLinkNotification::class);
    }

    public function test_a_submitted_password_is_ignored(): void
    {
        Notification::fake();

        $this->register(['password' => 'attacker-chosen', 'password_confirmation' => 'attacker-chosen']);

        $this->assertNull(Member::query()->where('email', 'new@example.com')->firstOrFail()->password);
    }

    public function test_the_emailed_link_logs_in_and_verifies_the_new_member(): void
    {
        Notification::fake();
        $this->register();
        $member = Member::query()->where('email', 'new@example.com')->firstOrFail();

        $this->get('/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/member');

        $this->assertAuthenticatedAs($member, 'member');
        $this->assertNotNull($member->fresh()->email_verified_at);
    }

    public function test_registering_an_existing_email_does_not_overwrite_it_and_sends_a_login_link(): void
    {
        Notification::fake();
        $existing = Member::factory()->create(['email' => 'new@example.com', 'name' => '原本的名字', 'organization' => '原本社群']);
        $hash = $existing->password;

        $this->register(['name' => '攻擊者', 'organization' => '攻擊社群'])->assertRedirect('/register/sent');

        $existing->refresh();
        $this->assertSame('原本的名字', $existing->name);
        $this->assertSame('原本社群', $existing->organization);
        $this->assertSame($hash, $existing->password);
        $this->assertSame(1, Member::query()->count());
        Notification::assertSentTo($existing, MemberLoginLinkNotification::class);
    }

    public function test_responses_are_identical_for_new_existing_and_deactivated_emails(): void
    {
        Notification::fake();
        Member::factory()->create(['email' => 'existing@example.com']);
        Member::factory()->inactive()->create(['email' => 'off@example.com']);

        $responses = collect(['fresh@example.com', 'existing@example.com', 'off@example.com'])
            ->map(fn (string $email) => $this->register(['email' => $email]));

        $responses->each->assertRedirect('/register/sent');
        $this->assertCount(1, $responses->map(fn ($response) => $response->getStatusCode())->unique());
    }

    public function test_a_deactivated_member_gets_no_email_and_is_not_changed(): void
    {
        Notification::fake();
        $off = Member::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->register(['email' => 'off@example.com']);

        Notification::assertNothingSent();
        $this->assertFalse($off->fresh()->is_active);
        $this->assertDatabaseCount('member_login_links', 0);
    }

    public function test_the_email_is_normalized(): void
    {
        Notification::fake();
        Member::factory()->create(['email' => 'alice@example.com']);

        $this->register(['email' => '  Alice@Example.COM ']);

        $this->assertSame(1, Member::query()->count());
    }

    public function test_validation_rejects_missing_name_and_bad_email(): void
    {
        $this->register(['name' => ''])->assertSessionHasErrors('name');
        $this->register(['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('members', 0);
    }

    public function test_organization_is_optional(): void
    {
        Notification::fake();

        $this->register(['organization' => ''])->assertSessionHasNoErrors();

        $this->assertNull(Member::query()->firstOrFail()->organization);
    }

    public function test_the_hourly_email_quota_is_shared_with_the_login_link_request(): void
    {
        Notification::fake();
        RateLimiter::clear(MemberLoginLink::throttleKey('shared@example.com'));

        for ($i = 0; $i < MemberLoginLink::HOURLY_LIMIT - 1; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.'.$i])
                ->post('/login/link', ['email' => 'shared@example.com']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.2.1.1'])->register(['email' => 'shared@example.com'])->assertRedirect('/register/sent');
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.1.2'])->register(['email' => 'shared@example.com'])->assertStatus(429);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->register(['email' => "u{$i}@example.com"]);
        }

        $this->register(['email' => 'u6@example.com'])->assertStatus(429);
    }

    public function test_the_sent_page_renders_in_both_languages_for_guests_only(): void
    {
        $this->get('/register/sent')->assertOk()->assertSee('登入連結已經寄出')->assertSee('noindex', false);
        $this->get('/en/register/sent')->assertOk()->assertSee('login link has been sent');

        $this->actingAs(Member::factory()->create(), 'member')->get('/register/sent')->assertRedirect('/member');
    }

    public function test_english_registration_sends_an_english_link_and_redirects_to_the_english_page(): void
    {
        Notification::fake();

        $this->register([], '/en/register')->assertRedirect('/en/register/sent');

        $member = Member::query()->firstOrFail();
        $url = null;
        Notification::assertSentTo($member, MemberLoginLinkNotification::class, function ($notification) use (&$url) {
            $url = $notification->url;

            return true;
        });
        $this->assertStringContainsString('/en/login/link/', $url);
    }

    public function test_pre_hijacking_an_email_leaves_the_attacker_with_nothing(): void
    {
        Notification::fake();
        $this->register(['email' => 'victim@example.com', 'password' => 'attacker-pass', 'password_confirmation' => 'attacker-pass']);
        $member = Member::query()->where('email', 'victim@example.com')->firstOrFail();

        $this->assertGuest('member');
        $this->post('/login', ['email' => 'victim@example.com', 'password' => 'attacker-pass'])->assertSessionHasErrors('email');

        // The victim proves ownership of the mailbox; the attacker still has no way in.
        $this->get('/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/member');
        auth('member')->logout();
        $this->post('/login', ['email' => 'victim@example.com', 'password' => 'attacker-pass'])->assertSessionHasErrors('email');
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/PasswordlessRegistrationTest.php`
Expected: FAIL，註冊仍要求密碼。

- [ ] **Step 4: 共用輔助方法**

`MemberController` 新增（`use App\Models\Member;`、`use App\Models\MemberLoginLink;`、`use App\Notifications\MemberLoginLinkNotification;`、`use Illuminate\Support\Facades\RateLimiter;`）：

```php
protected function takeLinkQuota(string $email): void
{
    $key = MemberLoginLink::throttleKey($email);

    abort_if(RateLimiter::tooManyAttempts($key, MemberLoginLink::HOURLY_LIMIT), 429);

    RateLimiter::hit($key, 3600);
}

protected function sendLoginLink(Member $member): void
{
    $token = MemberLoginLink::issueFor($member);

    $this->sendQuietly(fn () => $member->notify(
        (new MemberLoginLinkNotification($this->localizedRoute('member.login.link.consume', ['token' => $token])))
            ->locale(app()->getLocale()),
    ));
}
```

`LoginLinkController::request` 改為使用它們：

```php
public function request(Request $request): RedirectResponse
{
    $data = $request->validate(['email' => ['required', 'string', 'email']]);
    $email = Str::lower(trim($data['email']));

    $this->takeLinkQuota($email);

    $member = Member::query()->where('email', $email)->where('is_active', true)->first();

    if ($member !== null) {
        $this->sendLoginLink($member);
    }

    return back()->with('link_status', __('member.login.link_sent'));
}
```

並移除不再使用的 `use`。

- [ ] **Step 5: 註冊控制器**

`RegisterController` 完整取代：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegisterController extends MemberController
{
    public function create(): View
    {
        return view('member.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
        ]);

        $this->takeLinkQuota($data['email']);

        $member = Member::query()->createOrFirst(
            ['email' => $data['email']],
            ['name' => $data['name'], 'organization' => $data['organization'] ?? null],
        );

        if ($member->is_active) {
            $this->sendLoginLink($member);
        }

        return redirect($this->localizedRoute('member.register.sent'));
    }

    public function sent(): View
    {
        return view('member.register-sent');
    }
}
```

- [ ] **Step 6: 路由、頁面與文案**

`routes/web.php` 的訪客群組，在 `member.register.store` 之後加入：

```php
Route::get('/register/sent', [RegisterController::class, 'sent'])->name('member.register.sent'.$s);
```

`register.blade.php` 移除 `password` 與 `password_confirmation` 兩個欄位，保留姓名、Email、所屬社群，並在送出按鈕前加入一行說明：

```blade
<p class="text-sm text-ink/70">{{ __('member.register.no_password_note') }}</p>
```

`resources/views/member/register-sent.blade.php`：

```blade
@extends('member.layout')

@section('route', 'member.register')
@section('robots', 'noindex')
@section('title', __('member.register.sent_title'))
@section('description', __('member.register.sent_title'))
@section('heading', __('member.register.sent_heading'))
@section('intro', __('member.register.sent_body'))

@section('member_body')
    <p class="mt-8 text-sm">
        <a href="{{ route('member.login'.(app()->getLocale() === 'en' ? '.en' : '')) }}" class="font-semibold text-moss underline underline-offset-4">{{ __('member.register.login_link') }}</a>
    </p>
@endsection
```

`lang/zh_TW/member.php` 的 `register` 陣列加入：

```php
'no_password_note' => '註冊不需要密碼，我們會寄一封登入連結到你的信箱，登入後可以再設定密碼。',
'sent_title' => '請到信箱查看 | 共聚年會 GatherConf 2026',
'sent_heading' => '請到信箱查看',
'sent_body' => '如果這個 Email 可以註冊，登入連結已經寄出，請到信箱點擊連結，連結 15 分鐘內有效。',
```

`lang/en/member.php` 的 `register` 陣列加入：

```php
'no_password_note' => 'No password needed. We will email you a login link, and you can set a password after logging in.',
'sent_title' => 'Check your inbox | GatherConf 2026',
'sent_heading' => 'Check your inbox',
'sent_body' => 'If this email can be registered, a login link has been sent. Click it within 15 minutes.',
```

- [ ] **Step 7: 更新既有測試**

`tests/Feature/Member/RegisterTest.php` 取代為只保留仍成立的情境，其餘由 `PasswordlessRegistrationTest` 覆蓋。需要保留的有：註冊頁在兩種語言開啟、已登入會員不能開註冊頁、`rate limited per ip`。刪除這些與新行為衝突的測試：`test_registration_creates_an_active_unverified_member_and_logs_in`、`test_english_registration_redirects_to_the_english_notice`、`test_email_is_normalized_to_lower_case`、`test_duplicate_email_is_rejected_regardless_of_case`、`test_validation_rejects_bad_input`、`test_organization_is_optional`。

`tests/Feature/Member/MemberHardeningTest.php` 的 `test_registration_survives_a_mail_failure` 改為：

```php
public function test_registration_survives_a_mail_failure(): void
{
    $this->breakMailer();

    $this->post('/register', ['name' => '王小明', 'email' => 'new@example.com'])
        ->assertRedirect('/register/sent');

    $this->assertDatabaseHas('members', ['email' => 'new@example.com']);
    $this->assertGuest('member');
}
```

並執行 `grep -rn "'/register'" tests` 確認沒有其他測試以密碼註冊，有的一併改為以 factory 建立會員。

- [ ] **Step 8: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member tests/Feature/Friends tests/Feature/Messages`
Expected: PASS。

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources routes lang tests
git commit -m "Register members without a password and email them a login link"
```

---

### Task 3: 連結登入的標記、登入頁提示與重設密碼

**Files:**
- Modify: `app/Http/Controllers/Member/LoginLinkController.php`、`PasswordResetController.php`、`resources/views/member/login.blade.php`、`lang/zh_TW/member.php`、`lang/en/member.php`
- Test: `tests/Feature/Member/PasswordlessLoginTest.php`

**Interfaces:**
- Produces: 連結登入後 session 的 `member_link_login_at`（Unix 時間戳記）；登入頁提示；重設密碼成功同時驗證 Email。

- [ ] **Step 1: 產生測試檔**

```bash
php artisan make:test Member/PasswordlessLoginTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_link_login_records_the_time_in_the_session(): void
    {
        $this->freezeTime();
        $member = Member::factory()->passwordless()->unverified()->create();

        $this->get('/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/member');

        $this->assertSame(now()->timestamp, session('member_link_login_at'));
    }

    public function test_a_password_login_does_not_record_it(): void
    {
        Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/login', ['email' => 'm@example.com', 'password' => 'password']);

        $this->assertNull(session('member_link_login_at'));
    }

    public function test_the_login_page_explains_the_link_option_in_both_languages(): void
    {
        $this->get('/login')->assertOk()->assertSee('還沒有設定密碼');
        $this->get('/en/login')->assertOk()->assertSee('have not set a password')->assertDontSee('還沒有設定密碼');
    }

    public function test_resetting_the_password_of_a_passwordless_member_sets_it_and_verifies_the_email(): void
    {
        $member = Member::factory()->passwordless()->unverified()->create(['email' => 'm@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'm@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/login');

        $member->refresh();
        $this->assertTrue(Hash::check('brand-new-pass', $member->password));
        $this->assertNotNull($member->email_verified_at);
    }

    public function test_a_deactivated_member_can_reset_but_still_cannot_log_in(): void
    {
        $member = Member::factory()->passwordless()->inactive()->create(['email' => 'off@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'off@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ]);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'brand-new-pass'])
            ->assertSessionHasErrors(['email' => __('member.errors.inactive')]);
        $this->assertGuest('member');
    }

    public function test_forgot_password_works_for_passwordless_members(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $member = Member::factory()->passwordless()->create(['email' => 'm@example.com']);

        $this->post('/forgot-password', ['email' => 'm@example.com'])->assertSessionHas('status');

        \Illuminate\Support\Facades\Notification::assertSentTo($member, \App\Notifications\ResetMemberPasswordNotification::class);
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/PasswordlessLoginTest.php`
Expected: FAIL，session 沒有記錄、頁面沒有提示、重設密碼不驗證 Email。

- [ ] **Step 4: 實作**

`LoginLinkController::consume` 在 `$request->session()->regenerate();` 之後加入：

```php
$request->session()->put('member_link_login_at', now()->timestamp);
```

`PasswordResetController::update` 的重設回呼改為：

```php
function (Member $member, string $password): void {
    $member->forceFill(['password' => $password])->setRememberToken(Str::random(60));

    if (! $member->hasVerifiedEmail()) {
        $member->markEmailAsVerified();
    }

    $member->save();

    event(new PasswordReset($member));
},
```

`login.blade.php` 在密碼表單的「記住我」之前加入：

```blade
<p class="text-xs text-ink/70">{{ __('member.login.no_password_hint') }}</p>
```

`lang/zh_TW/member.php` 的 `login` 陣列加入 `'no_password_hint' => '還沒有設定密碼？請改用下方的登入連結。',`，`lang/en/member.php` 的 `login` 陣列加入 `'no_password_hint' => 'Have not set a password yet? Use the login link below instead.',`。

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member`
Expected: PASS。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources lang tests/Feature/Member/PasswordlessLoginTest.php
git commit -m "Record link logins, hint at the login link and verify email on password reset"
```

---

### Task 4: 設定與變更密碼

**Files:**
- Create: `app/Http/Controllers/Member/PasswordController.php`、`resources/views/member/password.blade.php`
- Modify: `routes/web.php`、`app/Providers/AppServiceProvider.php`、`lang/zh_TW/member.php`、`lang/en/member.php`、`lang/zh_TW/validation.php`
- Test: `tests/Feature/Member/PasswordSettingTest.php`

**Interfaces:**
- Consumes: Task 1 的 `hasPassword`，Task 3 的 `member_link_login_at`。
- Produces: `GET /member/password`（`member.password.edit`）、`PUT /member/password`（`member.password.change`，限流器 `member-password`）。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:controller Member/PasswordController --no-interaction
php artisan make:test Member/PasswordSettingTest --phpunit --no-interaction
```

`PasswordController` 先改為 `extends MemberController` 的空類別。

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function body(array $overrides = []): array
    {
        return array_merge(['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'], $overrides);
    }

    public function test_a_passwordless_member_sees_the_form_without_a_current_password_field(): void
    {
        $member = Member::factory()->passwordless()->create();

        $this->actingAs($member, 'member')->get('/member/password')
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertDontSee('name="current_password"', false)
            ->assertSee('noindex', false);
    }

    public function test_a_passwordless_member_can_set_a_password_and_then_log_in_with_it(): void
    {
        $member = Member::factory()->passwordless()->create(['email' => 'm@example.com']);

        $this->actingAs($member, 'member')->put('/member/password', $this->body())
            ->assertRedirect('/member/password')
            ->assertSessionHas('status', __('member.password.saved'));

        $this->assertTrue(Hash::check('brand-new-pass', $member->fresh()->password));

        auth('member')->logout();
        $this->post('/login', ['email' => 'm@example.com', 'password' => 'brand-new-pass'])->assertRedirect('/member');
    }

    public function test_a_member_with_a_password_must_enter_the_current_one(): void
    {
        $member = Member::factory()->create();
        $original = $member->password;

        $this->actingAs($member, 'member')->get('/member/password')->assertSee('name="current_password"', false);

        $this->actingAs($member, 'member')->put('/member/password', $this->body())
            ->assertSessionHasErrors('current_password');
        $this->actingAs($member, 'member')->put('/member/password', $this->body(['current_password' => 'wrong']))
            ->assertSessionHasErrors('current_password');

        $this->assertSame($original, $member->fresh()->password);

        $this->actingAs($member, 'member')->put('/member/password', $this->body(['current_password' => 'password']))
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('brand-new-pass', $member->fresh()->password));
    }

    public function test_a_recent_link_login_waives_the_current_password(): void
    {
        $this->freezeTime();
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')
            ->withSession(['member_link_login_at' => now()->subMinutes(10)->timestamp])
            ->put('/member/password', $this->body())
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $member->fresh()->password));
    }

    public function test_an_old_link_login_does_not_waive_the_current_password(): void
    {
        $this->freezeTime();
        $member = Member::factory()->create();
        $original = $member->password;

        $this->actingAs($member, 'member')
            ->withSession(['member_link_login_at' => now()->subMinutes(16)->timestamp])
            ->put('/member/password', $this->body())
            ->assertSessionHasErrors('current_password');

        $this->assertSame($original, $member->fresh()->password);
    }

    public function test_the_new_password_is_validated(): void
    {
        $member = Member::factory()->passwordless()->create();

        $this->actingAs($member, 'member')->put('/member/password', $this->body(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');
        $this->actingAs($member, 'member')->put('/member/password', $this->body(['password_confirmation' => 'different-pass']))
            ->assertSessionHasErrors('password');
        $this->actingAs($member, 'member')->put('/member/password', ['password' => ['x'], 'password_confirmation' => ['x']])
            ->assertSessionHasErrors('password');

        $this->assertNull($member->fresh()->password);
    }

    public function test_guests_unverified_and_deactivated_members_cannot_use_it(): void
    {
        $this->get('/member/password')->assertRedirect('/login');
        $this->put('/member/password', $this->body())->assertRedirect('/login');

        $this->actingAs(Member::factory()->passwordless()->unverified()->create(), 'member')
            ->get('/member/password')->assertRedirect('/email/verify');

        $this->actingAs(Member::factory()->passwordless()->inactive()->create(), 'member')
            ->get('/member/password')->assertRedirect('/login');
    }

    public function test_english_page_and_redirect(): void
    {
        $member = Member::factory()->passwordless()->create();

        $this->actingAs($member, 'member')->get('/en/member/password')->assertOk()->assertSee('Set a password');
        $this->actingAs($member, 'member')->put('/en/member/password', $this->body())->assertRedirect('/en/member/password');
    }

    public function test_changing_the_password_is_rate_limited(): void
    {
        $member = Member::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($member, 'member')->put('/member/password', $this->body(['current_password' => 'wrong']));
        }

        $this->actingAs($member, 'member')->put('/member/password', $this->body(['current_password' => 'wrong']))->assertStatus(429);
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/PasswordSettingTest.php`
Expected: FAIL，路由不存在。

- [ ] **Step 4: 限流器與路由**

`AppServiceProvider::boot()` 加入：

```php
RateLimiter::for('member-password', fn (Request $request) => Limit::perMinute(5)
    ->by('member-password|'.($request->user('member')?->getKey() ?? $request->ip())));
```

`routes/web.php` 的 `use` 區加入 `use App\Http\Controllers\Member\PasswordController;`，並在 `member.verified` 群組內加入：

```php
Route::get('/member/password', [PasswordController::class, 'edit'])->name('member.password.edit'.$s);
Route::put('/member/password', [PasswordController::class, 'update'])->middleware('throttle:member-password')->name('member.password.change'.$s);
```

- [ ] **Step 5: 控制器**

```php
<?php

namespace App\Http\Controllers\Member;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends MemberController
{
    private const LINK_LOGIN_WINDOW_SECONDS = 900;

    public function edit(Request $request): View
    {
        $member = $this->guard()->user();

        return view('member.password', [
            'hasPassword' => $member->hasPassword(),
            'needsCurrent' => $this->needsCurrentPassword($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $member = $this->guard()->user();
        $needsCurrent = $this->needsCurrentPassword($request);

        $rules = ['password' => ['required', 'string', 'confirmed', Password::min(8)]];

        if ($needsCurrent) {
            $rules['current_password'] = ['required', 'string'];
        }

        $data = $request->validate($rules);

        if ($needsCurrent && ! Hash::check($data['current_password'], $member->password)) {
            throw ValidationException::withMessages(['current_password' => __('member.password.current_wrong')]);
        }

        $member->forceFill(['password' => $data['password']])->save();

        $request->session()->regenerate();

        return redirect($this->localizedRoute('member.password.edit'))->with('status', __('member.password.saved'));
    }

    private function needsCurrentPassword(Request $request): bool
    {
        if (! $this->guard()->user()->hasPassword()) {
            return false;
        }

        $loggedInByLinkAt = $request->session()->get('member_link_login_at');

        return ! is_int($loggedInByLinkAt)
            || now()->timestamp - $loggedInByLinkAt > self::LINK_LOGIN_WINDOW_SECONDS;
    }
}
```

- [ ] **Step 6: 頁面與文案**

`resources/views/member/password.blade.php`：

```blade
@extends('member.layout')

@section('route', 'member.password.edit')
@section('robots', 'noindex')
@section('title', $hasPassword ? __('member.password.change_title') : __('member.password.set_title'))
@section('description', __('member.password.set_title'))
@section('heading', $hasPassword ? __('member.password.change_heading') : __('member.password.set_heading'))
@section('intro', $hasPassword ? '' : __('member.password.set_intro'))

@section('member_body')
    <form method="POST" action="{{ route('member.password.change'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        @method('PUT')
        @if ($needsCurrent)
            <x-member-field name="current_password" type="password" :label="__('member.password.current')" autocomplete="current-password" required />
        @endif
        <x-member-field name="password" type="password" :label="__('member.fields.password')" autocomplete="new-password" required />
        <x-member-field name="password_confirmation" type="password" :label="__('member.fields.password_confirmation')" autocomplete="new-password" required />
        <x-member-submit>{{ __('member.password.submit') }}</x-member-submit>
    </form>
@endsection
```

`lang/zh_TW/member.php` 新增：

```php
'password' => [
    'set_title' => '設定密碼 | 共聚年會 GatherConf 2026',
    'change_title' => '變更密碼 | 共聚年會 GatherConf 2026',
    'set_heading' => '設定密碼',
    'change_heading' => '變更密碼',
    'set_intro' => '你還沒有設定密碼，設定後就可以用 Email 與密碼登入。',
    'current' => '目前的密碼',
    'current_wrong' => '目前的密碼不正確。',
    'submit' => '儲存密碼',
    'saved' => '密碼已儲存。',
],
```

`lang/en/member.php` 新增：

```php
'password' => [
    'set_title' => 'Set a password | GatherConf 2026',
    'change_title' => 'Change password | GatherConf 2026',
    'set_heading' => 'Set a password',
    'change_heading' => 'Change password',
    'set_intro' => 'You have not set a password yet. Once you do, you can log in with your email and password.',
    'current' => 'Current password',
    'current_wrong' => 'The current password is incorrect.',
    'submit' => 'Save password',
    'saved' => 'Your password has been saved.',
],
```

`lang/zh_TW/validation.php` 的 `attributes` 加入 `'current_password' => '目前的密碼',`。

- [ ] **Step 7: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/PasswordSettingTest.php`
Expected: PASS，9 個測試。

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources routes lang tests/Feature/Member/PasswordSettingTest.php
git commit -m "Add a page to set or change the member password"
```

---

### Task 5: 會員中心提示、後台指示與整體驗證

**Files:**
- Modify: `app/Http/Controllers/Member/CenterController.php`、`resources/views/member/center.blade.php`、`lang/zh_TW/member.php`、`lang/en/member.php`、`app/Filament/Resources/Members/Schemas/MemberInfolist.php`、`app/Filament/Resources/Members/Tables/MembersTable.php`
- Test: `tests/Feature/Member/PasswordlessEntryPointsTest.php`

**Interfaces:**
- Consumes: Task 1 與 Task 4。
- Produces: 會員中心的設定密碼提示，與後台唯讀的「已設定密碼」指示。

- [ ] **Step 1: 產生測試檔並寫失敗的測試**

```bash
php artisan make:test Member/PasswordlessEntryPointsTest --phpunit --no-interaction
```

```php
<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordlessEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_passwordless_member_sees_the_set_password_prompt(): void
    {
        $member = Member::factory()->passwordless()->create();

        $this->actingAs($member, 'member')->get('/member')
            ->assertSee('data-set-password-prompt', false)
            ->assertSee(route('member.password.edit'), false);

        $this->actingAs($member, 'member')->get('/en/member')
            ->assertSee(route('member.password.edit.en'), false);
    }

    public function test_a_member_with_a_password_gets_a_change_link_but_no_prompt(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')->get('/member')
            ->assertDontSee('data-set-password-prompt', false)
            ->assertSee(route('member.password.edit'), false);
    }

    public function test_the_admin_sees_whether_a_password_is_set_but_never_the_hash(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'flamerecca711@gmail.com']));
        $with = Member::factory()->create();
        $without = Member::factory()->passwordless()->create();

        Livewire::test(ListMembers::class)->assertCanSeeTableRecords([$with, $without]);

        Livewire::test(ViewMember::class, ['record' => $with->getKey()])
            ->assertOk()
            ->assertDontSee($with->password);
    }
}
```

- [ ] **Step 2: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/PasswordlessEntryPointsTest.php`
Expected: FAIL，中心頁沒有提示與連結。

- [ ] **Step 3: 實作**

`CenterController::show` 的 `view()` 資料加入 `'hasPassword' => $member->hasPassword(),`。

`center.blade.php` 在好友入口連結之前加入：

```blade
@unless ($hasPassword)
    <p data-set-password-prompt class="rounded-2xl border border-copper p-4 text-sm">
        {{ __('member.center.set_password_prompt') }}
        <a href="{{ route('member.password.edit'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
           class="font-semibold text-moss underline underline-offset-4">{{ __('member.center.set_password') }}</a>
    </p>
@endunless
@if ($hasPassword)
    <a href="{{ route('member.password.edit'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
       class="text-sm font-medium text-moss underline underline-offset-4">{{ __('member.center.change_password') }}</a>
@endif
```

`lang/zh_TW/member.php` 的 `center` 陣列加入：

```php
'set_password_prompt' => '你還沒有設定密碼，設定後可以直接用 Email 與密碼登入。',
'set_password' => '設定密碼',
'change_password' => '變更密碼',
```

`lang/en/member.php` 的 `center` 陣列加入：

```php
'set_password_prompt' => 'You have not set a password yet. Set one to log in directly with your email and password.',
'set_password' => 'Set a password',
'change_password' => 'Change password',
```

`MemberInfolist` 在 `is_active` 之後加入：

```php
IconEntry::make('has_password')
    ->label('已設定密碼')
    ->state(fn (Member $record): bool => $record->hasPassword())
    ->boolean(),
```

並 `use App\Models\Member;`。`MembersTable` 的欄位在 `is_active` 之後加入：

```php
IconColumn::make('has_password')
    ->label('已設定密碼')
    ->state(fn (Member $record): bool => $record->hasPassword())
    ->boolean(),
```

並 `use App\Models\Member;`。

- [ ] **Step 4: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member`
Expected: PASS。

- [ ] **Step 5: 完整驗證**

Run: `php artisan test --compact`
Expected: 全部 PASS。

- [ ] **Step 6: 手動確認**

```bash
php artisan migrate --no-interaction
npm run build
```

用瀏覽器確認：

- 註冊頁沒有密碼欄位，送出後顯示「請到信箱查看」，信件寫入 `storage/logs/laravel.log`。
- 點擊信中連結後登入，會員中心顯示「設定密碼」提示，設定後可用密碼登入。
- 以沒有密碼的帳號輸入密碼登入，顯示與密碼錯誤相同的訊息。
- 中英文頁面在淺色與深色模式下都可讀。

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources lang tests/Feature/Member/PasswordlessEntryPointsTest.php
git commit -m "Prompt passwordless members to set a password and show it in the admin"
```

---

## 不在本計畫內

- 登出其他裝置、變更 Email、社群登入與雙重認證。
- 強制既有會員設定密碼。
- 正式環境的部署，migration 只是放寬欄位，不具破壞性。
