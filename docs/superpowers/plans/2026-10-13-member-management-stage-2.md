# 會員管理系統階段二實作計畫

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在官網提供會員的 Email 與密碼註冊、密碼登入、一次性登入連結登入、Email 驗證、忘記與重設密碼、登出，並讓後台可以代為寄送登入連結，同時落實停用會員即時失效、rate limiting 與登入連結清除排程。

**Architecture:** 沿用階段一的獨立 `member` guard。前台以 `App\Http\Controllers\Member` 底下的控制器處理流程，中英文各自一組具名路由，英文名稱加 `.en` 後綴。登入連結的建立與消耗集中在 `MemberLoginLink` 模型的靜態方法，消耗以單一條件式 `UPDATE` 達成原子性。通知信以三個獨立的 Notification 類別寄送，語系由建立時的 `app()->getLocale()` 決定。

**Tech Stack:** PHP 8.5、Laravel 13、Filament 4.12、PHPUnit 12、SQLite、Mailgun。

**Spec:** `docs/superpowers/specs/2026-10-13-member-management-design.md`，本計畫實作其中第 7 節前台、6.2 的寄送動作與第 10 節的階段二。階段一計畫為 `docs/superpowers/plans/2026-10-13-member-management-stage-1.md`，已完成並合併。

## Global Constraints

- 不新增任何 composer 依賴，不建立新的 `app` 底下的基礎資料夾。`app/Http/Middleware`、`app/Http/Controllers/Member`、`app/Notifications` 與 `app/Console/Commands` 是 Laravel 標準位置，不算新增基礎資料夾。
- 模型使用 `#[Fillable([...])]` 與 `#[Hidden([...])]` 屬性，見 `.ai/rules/models.md`。
- 所有檔案以 `php artisan make:` 指令建立，並傳入 `--no-interaction`。
- 所有測試使用 PHPUnit 類別，使用 `RefreshDatabase`，建立資料一律用 factory，測試前台頁面的類別在 `setUp()` 呼叫 `$this->withoutVite()`。
- 路由命名：中文版 `member.<name>`，英文版 `member.<name>.en`，中文路徑不加前綴，英文路徑加 `/en`。
- 一次性登入連結有效 15 分鐘，單次使用，資料庫只存 SHA-256 雜湊。
- 登入連結要求每個 Email 每小時最多 5 次，登入、註冊、忘記密碼每 IP 每分鐘最多 5 次，密碼登入每 Email 與 IP 組合每分鐘最多 5 次，超過回應 HTTP 429。
- 登入失敗一律顯示相同文字，要求登入連結與忘記密碼的回應不得透露 Email 是否已註冊。
- 前台文案放入 `lang/zh_TW/member.php` 與 `lang/en/member.php`，台灣用語，中英文之間留半形空格，中文句中不使用括號補充說明，引號用「」。
- 每次修改 PHP 檔案後，在 commit 前執行 `vendor/bin/pint --dirty --format agent`。
- 每次更新測試後執行該單一測試，不先跑整個測試套件。

## Review Focus

1. Email 大小寫：以 `Alice@Example.com` 註冊後，用 `alice@example.com` 密碼登入與要求登入連結都成功，大小寫不同的重複註冊被拒絕，Task 5、Task 6、Task 7 測試。
2. 登入連結重放：同一個連結第二次使用失敗，要求新連結後舊連結失效，Task 1 與 Task 7 測試。
3. 停用會員：密碼登入與連結登入皆被拒絕，已登入會員被停用後下一個請求即被登出，Task 2、Task 6、Task 7 測試。
4. 帳號列舉：要求登入連結與忘記密碼，對已註冊與未註冊的 Email 回應相同，且未註冊時不寄信，Task 7 與 Task 9 測試。
5. 驗證連結被竄改：簽章無效或雜湊不符回應 403，Task 8 測試。
6. 語系：英文頁面產生的通知信連結與重新導向都帶 `/en`，Task 3、Task 7 測試。

## Decisions Made In This Plan

這些是規格未明確寫出、計畫替你決定的細節，執行者視為已確定，不需要再詢問：

1. 登入連結的建立與消耗放在 `MemberLoginLink` 模型的靜態方法，不新增 `app/Services`。
2. 新增最小的會員中心頁 `/member`，只顯示姓名、Email、所屬社群或公司與登出按鈕，因為登入成功與登出需要落腳處。這不是個人資料編輯頁。
3. 註冊表單包含選填的所屬社群或公司。
4. 後台的寄送動作與前台共用同一個每小時 5 次的計數鍵，由 `MemberLoginLink::throttleKey()` 提供。
5. 點擊登入連結即直接登入，依規格 7.2，不加確認步驟。已知風險：部分郵件掃描器會預先請求連結而消耗它，若實際發生再另開需求加確認頁。
6. 在首頁頁尾加一個「會員登入」連結作為入口，並在 `robots.txt` 禁止索引會員頁面，會員頁面加 `noindex`。
7. 為 `zh_TW` 新增 `validation.php`，否則中文頁面的驗證訊息會退回英文。

---

## File Structure

| 檔案 | 責任 |
| :---- | :---- |
| `app/Models/MemberLoginLink.php` | 修改，新增 `issueFor`、`consume`、`throttleKey`，`revoke` 改為原子，`token_hash` 加入 Hidden |
| `app/Models/Member.php` | 修改，覆寫驗證信與重設密碼信的寄送 |
| `app/Http/Middleware/SetLocale.php` | 依路由設定語系 |
| `app/Http/Middleware/EnsureMemberIsActive.php` | 已停用會員即時登出 |
| `bootstrap/app.php` | 修改，別名與未登入、已登入導向 |
| `app/Providers/AppServiceProvider.php` | 修改，定義 rate limiter |
| `app/Notifications/MemberLoginLinkNotification.php` | 登入連結信 |
| `app/Notifications/VerifyMemberEmailNotification.php` | Email 驗證信 |
| `app/Notifications/ResetMemberPasswordNotification.php` | 重設密碼信 |
| `app/Http/Controllers/Member/MemberController.php` | 控制器基底，語系化路由輔助 |
| `app/Http/Controllers/Member/RegisterController.php` | 註冊 |
| `app/Http/Controllers/Member/LoginController.php` | 密碼登入與登出 |
| `app/Http/Controllers/Member/LoginLinkController.php` | 要求與使用登入連結 |
| `app/Http/Controllers/Member/EmailVerificationController.php` | Email 驗證 |
| `app/Http/Controllers/Member/PasswordResetController.php` | 忘記與重設密碼 |
| `app/Http/Controllers/Member/CenterController.php` | 會員中心 |
| `app/Console/Commands/PruneMemberLoginLinks.php` | 清除過期紀錄 |
| `routes/web.php` | 修改，新增會員路由表與 robots 規則 |
| `routes/console.php` | 修改，排程 |
| `resources/views/layouts/app.blade.php` | 修改，支援 `robots`、`zh_url`、`en_url` 區塊 |
| `resources/views/member/*.blade.php` | 各頁面 |
| `resources/views/components/member-field.blade.php`、`member-submit.blade.php` | 表單元件 |
| `resources/views/landing/footer.blade.php` | 修改，加會員登入連結 |
| `lang/zh_TW/member.php`、`lang/en/member.php`、`lang/zh_TW/validation.php` | 文案 |
| `app/Filament/Resources/Members/Actions/MemberActions.php` | 修改，新增 `sendLoginLink` |
| `tests/Feature/Member/*.php` | 對應各任務的測試 |

---

### Task 1: 登入連結的建立、消耗與原子作廢

**Files:**
- Modify: `app/Models/MemberLoginLink.php`
- Test: `tests/Feature/Member/MemberLoginLinkIssueTest.php`

**Interfaces:**
- Consumes: 階段一的 `MemberLoginLink::status()`、`isUsable()`、`revoke()`、`Member`。
- Produces:
  - `MemberLoginLink::VALID_MINUTES = 15`、`MemberLoginLink::HOURLY_LIMIT = 5`。
  - `MemberLoginLink::issueFor(Member $member): string`，回傳明文標記，並作廢該會員先前可使用的連結。
  - `MemberLoginLink::consume(string $token): ?Member`，成功原子地標記使用並回傳會員，失敗回傳 `null`。
  - `MemberLoginLink::throttleKey(string $email): string`。
  - `revoke()` 改為單一條件式 `UPDATE`，行為與回傳值不變。
  - `token_hash` 不會出現在 `toArray()`。

- [ ] **Step 1: 產生測試檔**

```bash
php artisan make:test Member/MemberLoginLinkIssueTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberLoginLinkIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_for_returns_plain_token_and_stores_only_its_hash(): void
    {
        $member = Member::factory()->create();

        $token = MemberLoginLink::issueFor($member);

        $this->assertSame(64, strlen($token));
        $this->assertDatabaseHas('member_login_links', [
            'member_id' => $member->id,
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('member_login_links', ['token_hash' => $token]);
    }

    public function test_issued_link_expires_in_fifteen_minutes(): void
    {
        $this->freezeTime();
        $member = Member::factory()->create();

        MemberLoginLink::issueFor($member);

        $link = $member->loginLinks()->first();
        $this->assertTrue($link->expires_at->equalTo(now()->addMinutes(15)));
    }

    public function test_issuing_a_new_link_revokes_previous_usable_links(): void
    {
        $member = Member::factory()->create();
        $first = MemberLoginLink::issueFor($member);

        $second = MemberLoginLink::issueFor($member);

        $this->assertNull(MemberLoginLink::consume($first));
        $this->assertTrue(MemberLoginLink::consume($second)->is($member));
    }

    public function test_consume_marks_link_used_and_cannot_be_replayed(): void
    {
        $member = Member::factory()->create();
        $token = MemberLoginLink::issueFor($member);

        $this->assertTrue(MemberLoginLink::consume($token)->is($member));
        $this->assertNull(MemberLoginLink::consume($token));
        $this->assertNotNull($member->loginLinks()->first()->used_at);
    }

    public function test_consume_rejects_unknown_expired_revoked_and_inactive(): void
    {
        $this->assertNull(MemberLoginLink::consume('does-not-exist'));

        $member = Member::factory()->create();
        $expiredToken = MemberLoginLink::issueFor($member);
        $member->loginLinks()->update(['expires_at' => now()->subMinute()]);
        $this->assertNull(MemberLoginLink::consume($expiredToken));

        $revoked = Member::factory()->create();
        $revokedToken = MemberLoginLink::issueFor($revoked);
        $revoked->loginLinks()->first()->revoke();
        $this->assertNull(MemberLoginLink::consume($revokedToken));

        $inactive = Member::factory()->inactive()->create();
        $inactiveToken = MemberLoginLink::issueFor($inactive);
        $this->assertNull(MemberLoginLink::consume($inactiveToken));
    }

    public function test_a_failed_consume_leaves_an_unusable_link_untouched(): void
    {
        $member = Member::factory()->inactive()->create();
        $token = MemberLoginLink::issueFor($member);

        MemberLoginLink::consume($token);

        $this->assertNull($member->loginLinks()->first()->used_at);
    }

    public function test_revoke_cannot_override_a_link_used_in_the_meantime(): void
    {
        $member = Member::factory()->create();
        MemberLoginLink::issueFor($member);
        $stale = $member->loginLinks()->first();

        $member->loginLinks()->update(['used_at' => now()]);

        $this->assertFalse($stale->revoke());
        $this->assertNull($stale->fresh()->revoked_at);
    }

    public function test_token_hash_is_hidden_from_serialization(): void
    {
        $link = MemberLoginLink::factory()->create();

        $this->assertArrayNotHasKey('token_hash', $link->toArray());
    }

    public function test_throttle_key_is_case_and_whitespace_insensitive(): void
    {
        $this->assertSame(
            MemberLoginLink::throttleKey('alice@example.com'),
            MemberLoginLink::throttleKey('  Alice@Example.com '),
        );
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/MemberLoginLinkIssueTest.php`
Expected: FAIL，`issueFor` 與 `throttleKey` 不存在。

- [ ] **Step 4: 修改 `MemberLoginLink`**

頂端新增 `use Illuminate\Database\Eloquent\Attributes\Hidden;`、`use Illuminate\Support\Facades\DB;`、`use Illuminate\Support\Str;`。類別屬性改為：

```php
#[Fillable(['member_id', 'token_hash', 'expires_at', 'used_at', 'revoked_at'])]
#[Hidden(['token_hash'])]
class MemberLoginLink extends Model
```

在 `STATUS_REVOKED` 常數後新增：

```php
public const VALID_MINUTES = 15;

public const HOURLY_LIMIT = 5;
```

把 `revoke()` 取代為：

```php
public function revoke(): bool
{
    $revoked = static::query()
        ->whereKey($this->getKey())
        ->whereNull('used_at')
        ->whereNull('revoked_at')
        ->where('expires_at', '>', now())
        ->update(['revoked_at' => now()]);

    if ($revoked !== 1) {
        return false;
    }

    $this->refresh();

    return true;
}
```

並新增三個靜態方法：

```php
public static function issueFor(Member $member): string
{
    $token = Str::random(64);

    DB::transaction(function () use ($member, $token): void {
        static::query()
            ->where('member_id', $member->getKey())
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->update(['revoked_at' => now()]);

        static::query()->create([
            'member_id' => $member->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::VALID_MINUTES),
        ]);
    });

    return $token;
}

public static function consume(string $token): ?Member
{
    $link = static::query()
        ->where('token_hash', hash('sha256', $token))
        ->with('member')
        ->first();

    if ($link === null || $link->member === null || ! $link->member->is_active) {
        return null;
    }

    $claimed = static::query()
        ->whereKey($link->getKey())
        ->whereNull('used_at')
        ->whereNull('revoked_at')
        ->where('expires_at', '>', now())
        ->update(['used_at' => now()]);

    return $claimed === 1 ? $link->member : null;
}

public static function throttleKey(string $email): string
{
    return 'member-login-link:'.Str::lower(trim($email));
}
```

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/MemberLoginLinkIssueTest.php tests/Feature/Member/MemberLoginLinkTest.php`
Expected: PASS，新舊測試皆通過。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/MemberLoginLink.php tests/Feature/Member/MemberLoginLinkIssueTest.php
git commit -m "Add login link issuing, atomic consume and revoke"
```

---

### Task 2: 語系與停用會員 middleware、導向、rate limiter

**Files:**
- Create: `app/Http/Middleware/SetLocale.php`、`app/Http/Middleware/EnsureMemberIsActive.php`
- Modify: `bootstrap/app.php`、`app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Member/MemberMiddlewareTest.php`

**Interfaces:**
- Consumes: `Member`。
- Produces:
  - middleware 別名 `member.locale:<locale>` 與 `member.active`。
  - 未登入存取 `auth:member` 路由時，導向 `member.login` 或 `member.login.en`，依路徑是否以 `en/` 開頭決定。
  - 已登入存取 `guest:member` 路由時，導向 `member.center` 或 `member.center.en`。
  - rate limiter `member-login`、`member-guest`。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:middleware SetLocale --no-interaction
php artisan make:middleware EnsureMemberIsActive --no-interaction
php artisan make:test Member/MemberMiddlewareTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

測試以臨時路由驗證，避免依賴後面任務才建立的控制器：

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MemberMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'member.locale:en'])->get('/en/_probe-locale', fn () => app()->getLocale());
        Route::middleware(['web', 'member.locale:zh_TW'])->get('/_probe-locale', fn () => app()->getLocale());
        Route::middleware(['web', 'member.active', 'auth:member'])->get('/_probe-auth', fn () => 'ok');
        Route::middleware(['web', 'member.active', 'auth:member'])->get('/en/_probe-auth', fn () => 'ok');
        Route::middleware(['web', 'guest:member'])->get('/_probe-guest', fn () => 'guest');
        Route::middleware(['web', 'guest:member'])->get('/en/_probe-guest', fn () => 'guest');
        Route::get('/login', fn () => 'login')->name('member.login');
        Route::get('/en/login', fn () => 'login')->name('member.login.en');
        Route::get('/member', fn () => 'center')->name('member.center');
        Route::get('/en/member', fn () => 'center')->name('member.center.en');
    }

    public function test_set_locale_applies_the_middleware_parameter(): void
    {
        $this->get('/en/_probe-locale')->assertSee('en');
        $this->get('/_probe-locale')->assertSee('zh_TW');
    }

    public function test_guests_are_redirected_to_the_matching_login_page(): void
    {
        $this->get('/_probe-auth')->assertRedirect(route('member.login'));
        $this->get('/en/_probe-auth')->assertRedirect(route('member.login.en'));
    }

    public function test_logged_in_members_are_redirected_away_from_guest_pages(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')->get('/_probe-guest')->assertRedirect(route('member.center'));
        $this->actingAs($member, 'member')->get('/en/_probe-guest')->assertRedirect(route('member.center.en'));
    }

    public function test_a_deactivated_member_is_logged_out_on_the_next_request(): void
    {
        $member = Member::factory()->create();
        $this->actingAs($member, 'member');
        $this->get('/_probe-auth')->assertOk();

        $member->update(['is_active' => false]);

        $this->get('/_probe-auth')->assertRedirect(route('member.login'));
        $this->assertGuest('member');
    }

    public function test_active_members_pass_through(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')->get('/_probe-auth')->assertOk();
        $this->assertTrue(Auth::guard('member')->check());
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/MemberMiddlewareTest.php`
Expected: FAIL，找不到 middleware 別名。

- [ ] **Step 4: 實作 middleware**

`app/Http/Middleware/SetLocale.php`：

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next, string $locale): Response
    {
        app()->setLocale($locale);

        return $next($request);
    }
}
```

`app/Http/Middleware/EnsureMemberIsActive.php`：

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = Auth::guard('member')->user();

        if ($member !== null && ! $member->is_active) {
            Auth::guard('member')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect(route($request->is('en/*') ? 'member.login.en' : 'member.login'))
                ->withErrors(['email' => __('member.errors.inactive')]);
        }

        return $next($request);
    }
}
```

- [ ] **Step 5: 註冊別名與導向**

`bootstrap/app.php` 的 `withMiddleware` 改為：

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'member.locale' => \App\Http\Middleware\SetLocale::class,
        'member.active' => \App\Http\Middleware\EnsureMemberIsActive::class,
    ]);

    $middleware->redirectGuestsTo(
        fn (Request $request) => route($request->is('en/*') ? 'member.login.en' : 'member.login'),
    );

    $middleware->redirectUsersTo(
        fn (Request $request) => route($request->is('en/*') ? 'member.center.en' : 'member.center'),
    );
})
```

`AppServiceProvider::boot()`：

```php
RateLimiter::for('member-login', fn (Request $request) => Limit::perMinute(5)
    ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

RateLimiter::for('member-guest', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
```

並加上 `use Illuminate\Cache\RateLimiting\Limit;`、`use Illuminate\Http\Request;`、`use Illuminate\Support\Facades\RateLimiter;`、`use Illuminate\Support\Str;`。

- [ ] **Step 6: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/MemberMiddlewareTest.php`
Expected: PASS，5 個測試。若因 `member.login` 在測試中被後面的正式路由重複註冊而失敗，改用 `Route::name()` 前確認 Task 4 之前尚未定義正式路由，不要刪除測試。

- [ ] **Step 7: 確認後台不受影響**

Run: `php artisan test --compact tests/Feature/Member`
Expected: 階段一的測試全部仍通過。

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app bootstrap tests/Feature/Member/MemberMiddlewareTest.php
git commit -m "Add member locale and active middleware, redirects and rate limiters"
```

---

### Task 3: 三種通知信與 Member 的寄送覆寫

**Files:**
- Create: `app/Notifications/MemberLoginLinkNotification.php`、`VerifyMemberEmailNotification.php`、`ResetMemberPasswordNotification.php`
- Modify: `app/Models/Member.php`、`lang/zh_TW/member.php`、`lang/en/member.php`
- Test: `tests/Feature/Member/MemberNotificationsTest.php`

**Interfaces:**
- Consumes: Task 1 的 `MemberLoginLink`；後面任務才建立的路由名稱 `member.verification.verify`、`member.password.reset`，通知中以 `route()` 呼叫，測試時於 `setUp` 補上臨時路由。
- Produces:
  - `new MemberLoginLinkNotification(string $url)`，公開屬性 `public string $url`，語系以 `->locale()` 指定。
  - `new VerifyMemberEmailNotification()`，建構時以 `app()->getLocale()` 決定語系，連結為簽章路由 `member.verification.verify[.en]`，有效 60 分鐘。
  - `new ResetMemberPasswordNotification(string $token)`，連結為 `member.password.reset[.en]`，帶 `token` 與 `email`。
  - `Member::sendEmailVerificationNotification()` 與 `Member::sendPasswordResetNotification($token)` 使用上述通知。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:notification MemberLoginLinkNotification --no-interaction
php artisan make:notification VerifyMemberEmailNotification --no-interaction
php artisan make:notification ResetMemberPasswordNotification --no-interaction
php artisan make:test Member/MemberNotificationsTest --phpunit --no-interaction
```

- [ ] **Step 2: 建立文案**

建立 `lang/zh_TW/member.php`，本任務先放入通知信需要的鍵，後續任務會在同一檔案追加其他鍵：

```php
<?php

return [
    'mail' => [
        'login_link' => [
            'subject' => '你的 GatherConf 登入連結',
            'intro' => '請點擊下方按鈕登入，連結 15 分鐘內有效，只能使用一次。',
            'action' => '登入 GatherConf',
            'outro' => '如果不是你要求的，請忽略這封信，不會有任何變更。',
        ],
        'verify' => [
            'subject' => '請驗證你的 Email',
            'intro' => '請點擊下方按鈕驗證你的 Email，連結 60 分鐘內有效。',
            'action' => '驗證 Email',
            'outro' => '如果你沒有註冊 GatherConf，請忽略這封信。',
        ],
        'reset' => [
            'subject' => '重設你的 GatherConf 密碼',
            'intro' => '請點擊下方按鈕重設密碼，連結 60 分鐘內有效。',
            'action' => '重設密碼',
            'outro' => '如果不是你要求的，請忽略這封信，密碼不會被更動。',
        ],
    ],
];
```

建立 `lang/en/member.php`：

```php
<?php

return [
    'mail' => [
        'login_link' => [
            'subject' => 'Your GatherConf login link',
            'intro' => 'Click the button below to log in. The link is valid for 15 minutes and can be used once.',
            'action' => 'Log in to GatherConf',
            'outro' => 'If you did not request this, you can ignore this email. Nothing will change.',
        ],
        'verify' => [
            'subject' => 'Verify your email',
            'intro' => 'Click the button below to verify your email. The link is valid for 60 minutes.',
            'action' => 'Verify email',
            'outro' => 'If you did not sign up for GatherConf, you can ignore this email.',
        ],
        'reset' => [
            'subject' => 'Reset your GatherConf password',
            'intro' => 'Click the button below to reset your password. The link is valid for 60 minutes.',
            'action' => 'Reset password',
            'outro' => 'If you did not request this, you can ignore this email. Your password will not change.',
        ],
    ],
];
```

- [ ] **Step 3: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Notifications\MemberLoginLinkNotification;
use App\Notifications\ResetMemberPasswordNotification;
use App\Notifications\VerifyMemberEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MemberNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'member.verification.verify' => '/email/verify/{id}/{hash}',
            'member.password.reset' => '/reset-password/{token}',
        ] as $name => $path) {
            Route::get($path, fn () => 'ok')->name($name);
            Route::get('/en'.$path, fn () => 'ok')->name($name.'.en');
        }
    }

    public function test_login_link_mail_uses_the_given_url_and_locale(): void
    {
        $member = Member::factory()->create();

        $zh = (new MemberLoginLinkNotification('https://example.test/login/link/abc'))->locale('zh_TW');
        $en = (new MemberLoginLinkNotification('https://example.test/en/login/link/abc'))->locale('en');

        $this->assertSame('你的 GatherConf 登入連結', $zh->toMail($member)->subject);
        $this->assertSame('Your GatherConf login link', $en->toMail($member)->subject);
        $this->assertSame('https://example.test/login/link/abc', $zh->toMail($member)->actionUrl);
    }

    public function test_verification_mail_uses_a_signed_route_for_the_current_locale(): void
    {
        $member = Member::factory()->unverified()->create();

        app()->setLocale('en');
        $notification = new VerifyMemberEmailNotification;
        $url = $notification->toMail($member)->actionUrl;

        $this->assertStringContainsString('/en/email/verify/'.$member->id.'/'.sha1($member->email), $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertSame('Verify your email', $notification->toMail($member)->subject);
    }

    public function test_reset_mail_contains_token_and_email_for_the_current_locale(): void
    {
        $member = Member::factory()->create();

        app()->setLocale('zh_TW');
        $url = (new ResetMemberPasswordNotification('tok123'))->toMail($member)->actionUrl;

        $this->assertStringContainsString('/reset-password/tok123', $url);
        $this->assertStringNotContainsString('/en/', $url);
        $this->assertStringContainsString('email='.urlencode($member->email), $url);
    }

    public function test_member_sends_the_custom_notifications(): void
    {
        Notification::fake();
        $member = Member::factory()->unverified()->create();

        $member->sendEmailVerificationNotification();
        $member->sendPasswordResetNotification('tok');

        Notification::assertSentTo($member, VerifyMemberEmailNotification::class);
        Notification::assertSentTo($member, ResetMemberPasswordNotification::class);
    }
}
```

- [ ] **Step 4: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/MemberNotificationsTest.php`
Expected: FAIL，通知尚未實作。

- [ ] **Step 5: 實作通知**

`app/Notifications/MemberLoginLinkNotification.php`：

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberLoginLinkNotification extends Notification
{
    public function __construct(public string $url) {}

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
            ->subject(__('member.mail.login_link.subject'))
            ->line(__('member.mail.login_link.intro'))
            ->action(__('member.mail.login_link.action'), $this->url)
            ->line(__('member.mail.login_link.outro'));
    }
}
```

`app/Notifications/VerifyMemberEmailNotification.php`：

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyMemberEmailNotification extends Notification
{
    public function __construct()
    {
        $this->locale(app()->getLocale());
    }

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
            ->subject(__('member.mail.verify.subject'))
            ->line(__('member.mail.verify.intro'))
            ->action(__('member.mail.verify.action'), $this->verificationUrl($notifiable))
            ->line(__('member.mail.verify.outro'));
    }

    protected function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'member.verification.verify'.($this->locale === 'en' ? '.en' : ''),
            now()->addMinutes(60),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
        );
    }
}
```

`app/Notifications/ResetMemberPasswordNotification.php`：

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetMemberPasswordNotification extends Notification
{
    public function __construct(public string $token)
    {
        $this->locale(app()->getLocale());
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('member.password.reset'.($this->locale === 'en' ? '.en' : ''), [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(__('member.mail.reset.subject'))
            ->line(__('member.mail.reset.intro'))
            ->action(__('member.mail.reset.action'), $url)
            ->line(__('member.mail.reset.outro'));
    }
}
```

- [ ] **Step 6: 覆寫 Member 的寄送**

在 `app/Models/Member.php` 的 `loginLinks()` 之前加入，並 `use App\Notifications\ResetMemberPasswordNotification;`、`use App\Notifications\VerifyMemberEmailNotification;`：

```php
public function sendEmailVerificationNotification(): void
{
    $this->notify(new VerifyMemberEmailNotification);
}

public function sendPasswordResetNotification($token): void
{
    $this->notify(new ResetMemberPasswordNotification($token));
}
```

- [ ] **Step 7: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/MemberNotificationsTest.php`
Expected: PASS，4 個測試。

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app lang tests/Feature/Member/MemberNotificationsTest.php
git commit -m "Add member login link, verification and reset notifications"
```

---

### Task 4: 前台共用基礎，路由表、版面、元件與文案

**Files:**
- Create: `app/Http/Controllers/Member/MemberController.php`、及後續任務各控制器的空殼，見 Step 2
- Create: `resources/views/member/layout.blade.php`、`resources/views/components/member-field.blade.php`、`resources/views/components/member-submit.blade.php`、`lang/zh_TW/validation.php`
- Modify: `routes/web.php`、`resources/views/layouts/app.blade.php`、`lang/zh_TW/member.php`、`lang/en/member.php`
- Test: `tests/Feature/Member/MemberRoutesTest.php`

**Interfaces:**
- Consumes: Task 2 的 middleware 別名與 rate limiter。
- Produces:
  - 完整會員路由表，名稱見規格 7.1 與下表。
  - `MemberController::localizedRoute(string $name, array $parameters = []): string`，`MemberController::guard(): StatefulGuard`。
  - `member.layout` 版面：`@section('member_body')` 放頁面主體，支援 `route`、`zh_url`、`en_url`、`title`、`description`。
  - `layouts.app` 新增可選區塊 `robots`、`zh_url`、`en_url`。
  - 元件 `<x-member-field name label type value autocomplete required />` 與 `<x-member-submit>`。

| 名稱 | 方法與路徑 | 控制器方法 |
| :---- | :---- | :---- |
| `member.register` | GET `/register` | `RegisterController@create` |
| `member.register.store` | POST `/register` | `RegisterController@store` |
| `member.login` | GET `/login` | `LoginController@create` |
| `member.login.store` | POST `/login` | `LoginController@store` |
| `member.login.link.request` | POST `/login/link` | `LoginLinkController@request` |
| `member.login.link.consume` | GET `/login/link/{token}` | `LoginLinkController@consume` |
| `member.password.request` | GET `/forgot-password` | `PasswordResetController@create` |
| `member.password.email` | POST `/forgot-password` | `PasswordResetController@store` |
| `member.password.reset` | GET `/reset-password/{token}` | `PasswordResetController@edit` |
| `member.password.update` | POST `/reset-password` | `PasswordResetController@update` |
| `member.logout` | POST `/logout` | `LoginController@destroy` |
| `member.center` | GET `/member` | `CenterController@show` |
| `member.verification.notice` | GET `/email/verify` | `EmailVerificationController@notice` |
| `member.verification.send` | POST `/email/verification-notification` | `EmailVerificationController@send` |
| `member.verification.verify` | GET `/email/verify/{id}/{hash}` | `EmailVerificationController@verify` |

英文版名稱再加 `.en`，路徑加 `/en` 前綴。

- [ ] **Step 1: 產生控制器**

```bash
for c in Register Login LoginLink EmailVerification PasswordReset Center; do
  php artisan make:controller "Member/${c}Controller" --no-interaction
done
php artisan make:controller Member/MemberController --no-interaction
php artisan make:test Member/MemberRoutesTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫基底控制器**

`app/Http/Controllers/Member/MemberController.php` 完整取代：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;

abstract class MemberController extends Controller
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function localizedRoute(string $name, array $parameters = []): string
    {
        return route($name.(app()->getLocale() === 'en' ? '.en' : ''), $parameters);
    }

    protected function guard(): StatefulGuard
    {
        return Auth::guard('member');
    }
}
```

其餘六個控制器改為 `extends MemberController`，並在各自任務填入方法。為了讓本任務的路由測試可以在控制器尚未實作前通過，各控制器先保持為空類別。

- [ ] **Step 3: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MemberRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_member_route_exists_in_both_languages_with_matching_paths(): void
    {
        $names = [
            'member.register' => '/register',
            'member.register.store' => '/register',
            'member.login' => '/login',
            'member.login.store' => '/login',
            'member.login.link.request' => '/login/link',
            'member.password.request' => '/forgot-password',
            'member.password.email' => '/forgot-password',
            'member.password.update' => '/reset-password',
            'member.logout' => '/logout',
            'member.center' => '/member',
            'member.verification.notice' => '/email/verify',
            'member.verification.send' => '/email/verification-notification',
        ];

        foreach ($names as $name => $path) {
            $this->assertSame($path, '/'.Route::getRoutes()->getByName($name)->uri());
            $this->assertSame('/en'.$path, '/'.Route::getRoutes()->getByName($name.'.en')->uri());
        }

        $this->assertSame('/login/link/abc', route('member.login.link.consume', ['token' => 'abc'], false));
        $this->assertSame('/en/reset-password/abc', route('member.password.reset.en', ['token' => 'abc'], false));
        $this->assertSame('/email/verify/1/h', route('member.verification.verify', ['id' => 1, 'hash' => 'h'], false));
    }

    public function test_guest_routes_carry_the_matching_locale_middleware(): void
    {
        $zh = Route::getRoutes()->getByName('member.login');
        $en = Route::getRoutes()->getByName('member.login.en');

        $this->assertContains('member.locale:zh_TW', $zh->gatherMiddleware());
        $this->assertContains('member.locale:en', $en->gatherMiddleware());
        $this->assertContains('guest:member', $zh->gatherMiddleware());
    }

    public function test_sensitive_routes_are_rate_limited(): void
    {
        $this->assertContains('throttle:member-login', Route::getRoutes()->getByName('member.login.store')->gatherMiddleware());
        $this->assertContains('throttle:member-guest', Route::getRoutes()->getByName('member.register.store')->gatherMiddleware());
        $this->assertContains('throttle:member-guest', Route::getRoutes()->getByName('member.login.link.request')->gatherMiddleware());
        $this->assertContains('throttle:member-guest', Route::getRoutes()->getByName('member.password.email')->gatherMiddleware());
    }

    public function test_the_verification_link_requires_a_valid_signature(): void
    {
        $this->assertContains('signed', Route::getRoutes()->getByName('member.verification.verify')->gatherMiddleware());
    }
}
```

- [ ] **Step 4: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/MemberRoutesTest.php`
Expected: FAIL，路由不存在。

- [ ] **Step 5: 路由表**

在 `routes/web.php` 的 `use` 區加入六個控制器的 `use`，並在 `Route::get('/robots.txt'...)` 之前加入：

```php
foreach (['' => 'zh_TW', 'en' => 'en'] as $prefix => $locale) {
    $s = $locale === 'en' ? '.en' : '';

    Route::prefix($prefix)->middleware(['member.locale:'.$locale, 'member.active'])->group(function () use ($s): void {
        Route::middleware('guest:member')->group(function () use ($s): void {
            Route::get('/register', [RegisterController::class, 'create'])->name('member.register'.$s);
            Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:member-guest')->name('member.register.store'.$s);
            Route::get('/login', [LoginController::class, 'create'])->name('member.login'.$s);
            Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:member-login')->name('member.login.store'.$s);
            Route::post('/login/link', [LoginLinkController::class, 'request'])->middleware('throttle:member-guest')->name('member.login.link.request'.$s);
            Route::get('/login/link/{token}', [LoginLinkController::class, 'consume'])->middleware('throttle:20,1')->name('member.login.link.consume'.$s);
            Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('member.password.request'.$s);
            Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:member-guest')->name('member.password.email'.$s);
            Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('member.password.reset'.$s);
            Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:member-guest')->name('member.password.update'.$s);
        });

        Route::middleware('auth:member')->group(function () use ($s): void {
            Route::post('/logout', [LoginController::class, 'destroy'])->name('member.logout'.$s);
            Route::get('/member', [CenterController::class, 'show'])->name('member.center'.$s);
            Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('member.verification.notice'.$s);
            Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('member.verification.send'.$s);
        });

        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('member.verification.verify'.$s);
    });
}
```

- [ ] **Step 6: 修改版面，新增可選區塊**

`resources/views/layouts/app.blade.php` 頂端的 `@php` 區塊中，把 `$zhUrl` 與 `$enUrl` 兩行改為：

```php
$zhUrl = trim($__env->yieldContent('zh_url')) ?: route($routeBase);
$enUrl = trim($__env->yieldContent('en_url')) ?: route($routeBase.'.en');
```

並在 `<meta name="description" ...>` 之後加入：

```blade
@hasSection('robots')
    <meta name="robots" content="@yield('robots')">
@endif
```

- [ ] **Step 7: 會員共用版面與元件**

`resources/views/member/layout.blade.php`：

```blade
@extends('layouts.app')

@section('content')
    @php
        $isEnglish = app()->getLocale() === 'en';
        $routeBase = trim($__env->yieldContent('route'));
        $zhUrl = trim($__env->yieldContent('zh_url')) ?: route($routeBase);
        $enUrl = trim($__env->yieldContent('en_url')) ?: route($routeBase.'.en');
    @endphp
    <x-subpage-header :switch-url="$isEnglish ? $zhUrl : $enUrl"
                      :switch-label="__('member.switch_label')" :back-label="__('member.back')" />
    <main class="mx-auto max-w-md px-4 py-12">
        <h1 class="text-4xl font-bold tracking-tight">@yield('heading')</h1>
        @hasSection('intro')
            <p class="mt-4 leading-relaxed text-ink/80">@yield('intro')</p>
        @endif
        @if (session('status') || session('link_status'))
            <p class="mt-6 rounded-2xl border border-moss/60 bg-parchment-dark p-4 text-sm" role="status">
                {{ session('status') ?? session('link_status') }}
            </p>
        @endif
        @if ($errors->has('link'))
            <p class="mt-6 rounded-2xl border border-copper p-4 text-sm text-copper" role="alert">{{ $errors->first('link') }}</p>
        @endif
        @yield('member_body')
    </main>
    @include('landing.footer')
@endsection
```

`resources/views/components/member-field.blade.php`：

```blade
@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null, 'required' => false])
<div>
    <label for="{{ $name }}" class="block text-sm font-semibold">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @required($required)
           class="mt-2 w-full rounded-xl border border-ink/30 bg-parchment px-4 py-3 focus:border-moss focus:outline-none focus:ring-2 focus:ring-moss/40">
    @error($name)
        <p class="mt-2 text-sm text-copper" role="alert">{{ $message }}</p>
    @enderror
</div>
```

`resources/views/components/member-submit.blade.php`：

```blade
<button type="submit"
        class="rounded-full bg-moss px-8 py-3 font-semibold text-on-accent shadow transition hover:bg-ink">
    {{ $slot }}
</button>
```

- [ ] **Step 8: 共用文案與驗證訊息**

在 `lang/zh_TW/member.php` 最上層補入（保留 Task 3 的 `mail`）：

```php
'back' => '回到首頁',
'switch_label' => 'English',
'fields' => [
    'name' => '姓名',
    'email' => 'Email',
    'password' => '密碼',
    'password_confirmation' => '確認密碼',
    'organization' => '所屬社群或公司，選填',
],
'errors' => [
    'failed' => 'Email 或密碼不正確。',
    'inactive' => '此帳號已被停用，如有疑問請聯絡我們。',
    'link_invalid' => '這個登入連結已失效，請重新要求一個。',
],
```

在 `lang/en/member.php` 補入：

```php
'back' => 'Back to home',
'switch_label' => '中文',
'fields' => [
    'name' => 'Name',
    'email' => 'Email',
    'password' => 'Password',
    'password_confirmation' => 'Confirm password',
    'organization' => 'Community or company, optional',
],
'errors' => [
    'failed' => 'The email or password is incorrect.',
    'inactive' => 'This account has been deactivated. Please contact us if you have questions.',
    'link_invalid' => 'This login link is no longer valid. Please request a new one.',
],
```

`lang/zh_TW/validation.php`：

```php
<?php

return [
    'required' => ':attribute 為必填。',
    'email' => ':attribute 必須是有效的 Email。',
    'confirmed' => ':attribute 與確認欄位不一致。',
    'unique' => '這個 :attribute 已經被使用。',
    'max' => [
        'string' => ':attribute 不可超過 :max 個字元。',
    ],
    'min' => [
        'string' => ':attribute 至少需要 :min 個字元。',
    ],
    'password' => [
        'letters' => ':attribute 必須包含至少一個字母。',
        'mixed' => ':attribute 必須包含大小寫字母。',
        'numbers' => ':attribute 必須包含至少一個數字。',
        'symbols' => ':attribute 必須包含至少一個符號。',
        'uncompromised' => '這個 :attribute 曾出現在外洩資料中，請換一個。',
    ],
    'attributes' => [
        'name' => '姓名',
        'email' => 'Email',
        'password' => '密碼',
        'organization' => '所屬社群或公司',
    ],
];
```

- [ ] **Step 9: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/MemberRoutesTest.php tests/Feature/Member/MemberMiddlewareTest.php`
Expected: PASS。若 Task 2 的臨時路由與正式路由名稱衝突，把 Task 2 測試中的臨時路由名稱改為 `probe.login`、`probe.center` 等並同步調整該測試的 redirect 斷言，不要刪除測試，並以 `Ruling:` 記錄。

- [ ] **Step 10: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes resources lang tests/Feature/Member
git commit -m "Add member route table, layout, components and shared copy"
```

---

### Task 5: 註冊

**Files:**
- Modify: `app/Http/Controllers/Member/RegisterController.php`、`lang/zh_TW/member.php`、`lang/en/member.php`
- Create: `resources/views/member/register.blade.php`
- Test: `tests/Feature/Member/RegisterTest.php`

**Interfaces:**
- Consumes: Task 3 的 `VerifyMemberEmailNotification`、Task 4 的路由與版面。
- Produces: `GET/POST /register` 與 `/en/register`；成功後會員已登入並導向 `member.verification.notice[.en]`。

- [ ] **Step 1: 文案**

`lang/zh_TW/member.php` 新增：

```php
'register' => [
    'title' => '註冊會員 | 共聚年會 GatherConf 2026',
    'description' => '註冊成為共聚年會會員。',
    'heading' => '註冊會員',
    'submit' => '註冊',
    'have_account' => '已經有帳號了？',
    'login_link' => '前往登入',
],
```

`lang/en/member.php` 新增：

```php
'register' => [
    'title' => 'Sign up | GatherConf 2026',
    'description' => 'Sign up as a GatherConf member.',
    'heading' => 'Sign up',
    'submit' => 'Sign up',
    'have_account' => 'Already have an account?',
    'login_link' => 'Log in',
],
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Notifications\VerifyMemberEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '王小明',
            'email' => 'new@example.com',
            'organization' => 'Kotlin Taiwan',
            'password' => 'a-strong-pass',
            'password_confirmation' => 'a-strong-pass',
        ], $overrides);
    }

    public function test_registration_pages_render_in_both_languages(): void
    {
        $this->get('/register')->assertOk()->assertSee('註冊會員')->assertSee('noindex', false);
        $this->get('/en/register')->assertOk()->assertSee('Sign up')->assertDontSee('註冊會員');
    }

    public function test_registration_creates_an_active_unverified_member_and_logs_in(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload())->assertRedirect('/email/verify');

        $member = Member::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($member, 'member');
        $this->assertTrue($member->is_active);
        $this->assertNull($member->email_verified_at);
        $this->assertSame('Kotlin Taiwan', $member->organization);
        Notification::assertSentTo($member, VerifyMemberEmailNotification::class);
    }

    public function test_english_registration_redirects_to_the_english_notice(): void
    {
        Notification::fake();

        $this->post('/en/register', $this->payload())->assertRedirect('/en/email/verify');
    }

    public function test_email_is_normalized_to_lower_case(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload(['email' => 'Alice@Example.COM']));

        $this->assertDatabaseHas('members', ['email' => 'alice@example.com']);
    }

    public function test_duplicate_email_is_rejected_regardless_of_case(): void
    {
        Member::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', $this->payload(['email' => 'Taken@Example.com']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Member::query()->count());
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->post('/register', $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/register', $this->payload(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->post('/register', $this->payload(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', $this->payload(['password_confirmation' => 'different-pass']))->assertSessionHasErrors('password');
        $this->assertSame(0, Member::query()->count());
    }

    public function test_organization_is_optional(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload(['organization' => '']))->assertSessionHasNoErrors();

        $this->assertNull(Member::query()->firstOrFail()->organization);
    }

    public function test_a_logged_in_member_cannot_open_the_registration_page(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')->get('/register')->assertRedirect('/member');
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $this->payload(['email' => "u{$i}@example.com"]));
            auth('member')->logout();
        }

        $this->post('/register', $this->payload(['email' => 'u6@example.com']))->assertStatus(429);
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/RegisterTest.php`
Expected: FAIL，控制器方法不存在。

- [ ] **Step 4: 實作控制器**

`app/Http/Controllers/Member/RegisterController.php`：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('members', 'email')],
            'organization' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $member = Member::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'organization' => $data['organization'] ?? null,
            'password' => $data['password'],
        ]);

        event(new Registered($member));

        $this->guard()->login($member);
        $request->session()->regenerate();

        return redirect($this->localizedRoute('member.verification.notice'));
    }
}
```

- [ ] **Step 5: 實作頁面**

`resources/views/member/register.blade.php`：

```blade
@extends('member.layout')

@section('route', 'member.register')
@section('robots', 'noindex')
@section('title', __('member.register.title'))
@section('description', __('member.register.description'))
@section('heading', __('member.register.heading'))

@section('member_body')
    <form method="POST" action="{{ route('member.register.store'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <x-member-field name="name" :label="__('member.fields.name')" autocomplete="name" required />
        <x-member-field name="email" type="email" :label="__('member.fields.email')" autocomplete="email" required />
        <x-member-field name="organization" :label="__('member.fields.organization')" autocomplete="organization" />
        <x-member-field name="password" type="password" :label="__('member.fields.password')" autocomplete="new-password" required />
        <x-member-field name="password_confirmation" type="password" :label="__('member.fields.password_confirmation')" autocomplete="new-password" required />
        <x-member-submit>{{ __('member.register.submit') }}</x-member-submit>
    </form>
    <p class="mt-6 text-sm">
        {{ __('member.register.have_account') }}
        <a href="{{ route('member.login'.(app()->getLocale() === 'en' ? '.en' : '')) }}" class="font-semibold text-moss underline underline-offset-4">{{ __('member.register.login_link') }}</a>
    </p>
@endsection
```

- [ ] **Step 6: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/RegisterTest.php`
Expected: PASS，9 個測試。`/member` 與登入頁的實作在後續任務，此任務的 `assertRedirect('/member')` 只驗證 redirect 目標，不需要頁面存在。

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources lang tests/Feature/Member/RegisterTest.php
git commit -m "Add member registration"
```

---

### Task 6: 密碼登入、登出與會員中心

**Files:**
- Modify: `app/Http/Controllers/Member/LoginController.php`、`CenterController.php`、`lang/zh_TW/member.php`、`lang/en/member.php`
- Create: `resources/views/member/login.blade.php`、`resources/views/member/center.blade.php`
- Test: `tests/Feature/Member/LoginTest.php`

**Interfaces:**
- Consumes: Task 4 的路由與版面。
- Produces: `GET/POST /login`、`POST /logout`、`GET /member` 與英文版；登入頁同時包含寄送登入連結的表單，表單 action 為 `member.login.link.request[.en]`，由 Task 7 處理。

- [ ] **Step 1: 文案**

`lang/zh_TW/member.php` 新增：

```php
'login' => [
    'title' => '會員登入 | 共聚年會 GatherConf 2026',
    'description' => '登入共聚年會會員。',
    'heading' => '會員登入',
    'password_heading' => '使用密碼登入',
    'submit' => '登入',
    'remember' => '記住我',
    'forgot' => '忘記密碼？',
    'register_prompt' => '還沒有帳號？',
    'register_link' => '註冊會員',
    'link_heading' => '或是寄送登入連結',
    'link_intro' => '輸入 Email，我們會寄一封一次性登入連結給你，不需要輸入密碼。',
    'link_submit' => '寄送登入連結',
    'link_sent' => '如果這個 Email 已註冊，登入連結已經寄出，請到信箱查看。',
],
'center' => [
    'title' => '會員中心 | 共聚年會 GatherConf 2026',
    'heading' => '會員中心',
    'welcome' => '嗨，:name',
    'organization' => '所屬社群或公司',
    'not_set' => '未填寫',
    'logout' => '登出',
],
```

`lang/en/member.php` 新增：

```php
'login' => [
    'title' => 'Log in | GatherConf 2026',
    'description' => 'Log in as a GatherConf member.',
    'heading' => 'Log in',
    'password_heading' => 'Log in with your password',
    'submit' => 'Log in',
    'remember' => 'Remember me',
    'forgot' => 'Forgot your password?',
    'register_prompt' => 'No account yet?',
    'register_link' => 'Sign up',
    'link_heading' => 'Or get a login link',
    'link_intro' => 'Enter your email and we will send you a one-time login link, no password needed.',
    'link_submit' => 'Send login link',
    'link_sent' => 'If this email is registered, a login link has been sent. Please check your inbox.',
],
'center' => [
    'title' => 'Member center | GatherConf 2026',
    'heading' => 'Member center',
    'welcome' => 'Hi, :name',
    'organization' => 'Community or company',
    'not_set' => 'Not set',
    'logout' => 'Log out',
],
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_pages_render_in_both_languages(): void
    {
        $this->get('/login')->assertOk()->assertSee('會員登入')->assertSee('寄送登入連結');
        $this->get('/en/login')->assertOk()->assertSee('Log in')->assertSee('Send login link')->assertDontSee('會員登入');
    }

    public function test_member_can_log_in_with_password(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/login', ['email' => 'm@example.com', 'password' => 'password'])
            ->assertRedirect('/member');

        $this->assertAuthenticatedAs($member, 'member');
    }

    public function test_email_is_matched_case_insensitively(): void
    {
        Member::factory()->create(['email' => 'alice@example.com']);

        $this->post('/login', ['email' => 'Alice@Example.com', 'password' => 'password'])->assertRedirect('/member');
    }

    public function test_wrong_password_and_unknown_email_show_the_same_message(): void
    {
        Member::factory()->create(['email' => 'm@example.com']);

        $wrong = $this->post('/login', ['email' => 'm@example.com', 'password' => 'nope']);
        $unknown = $this->post('/login', ['email' => 'ghost@example.com', 'password' => 'nope']);

        $wrong->assertSessionHasErrors(['email' => __('member.errors.failed')]);
        $unknown->assertSessionHasErrors(['email' => __('member.errors.failed')]);
        $this->assertGuest('member');
    }

    public function test_an_inactive_member_cannot_log_in_even_with_the_right_password(): void
    {
        Member::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('member.errors.inactive')]);

        $this->assertGuest('member');
    }

    public function test_an_inactive_member_is_not_told_so_with_a_wrong_password(): void
    {
        Member::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => __('member.errors.failed')]);
    }

    public function test_english_login_redirects_to_the_english_center(): void
    {
        Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/en/login', ['email' => 'm@example.com', 'password' => 'password'])->assertRedirect('/en/member');
    }

    public function test_login_is_rate_limited_per_email_and_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'm@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'm@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest('member');
    }

    public function test_center_shows_member_details_to_verified_members(): void
    {
        $member = Member::factory()->create(['name' => '王小明', 'organization' => null]);

        $this->actingAs($member, 'member')->get('/member')
            ->assertOk()
            ->assertSee('王小明')
            ->assertSee($member->email)
            ->assertSee('未填寫');
    }

    public function test_center_sends_unverified_members_to_the_verification_notice(): void
    {
        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/member')
            ->assertRedirect('/email/verify');

        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/en/member')
            ->assertRedirect('/en/email/verify');
    }

    public function test_center_requires_login(): void
    {
        $this->get('/member')->assertRedirect('/login');
        $this->get('/en/member')->assertRedirect('/en/login');
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/LoginTest.php`
Expected: FAIL，控制器方法不存在。

- [ ] **Step 4: 實作控制器**

`LoginController`：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends MemberController
{
    public function create(): View
    {
        return view('member.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $member = Member::query()->where('email', Str::lower(trim($credentials['email'])))->first();

        if ($member === null || ! Hash::check($credentials['password'], $member->password)) {
            throw ValidationException::withMessages(['email' => __('member.errors.failed')]);
        }

        if (! $member->is_active) {
            throw ValidationException::withMessages(['email' => __('member.errors.inactive')]);
        }

        $this->guard()->login($member, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended($this->localizedRoute('member.center'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route(app()->getLocale() === 'en' ? 'landing.en' : 'landing'));
    }
}
```

`CenterController`：

```php
<?php

namespace App\Http\Controllers\Member;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CenterController extends MemberController
{
    public function show(): View|RedirectResponse
    {
        $member = $this->guard()->user();

        if (! $member->hasVerifiedEmail()) {
            return redirect($this->localizedRoute('member.verification.notice'));
        }

        return view('member.center', ['member' => $member]);
    }
}
```

- [ ] **Step 5: 實作頁面**

`resources/views/member/login.blade.php`：

```blade
@extends('member.layout')

@section('route', 'member.login')
@section('robots', 'noindex')
@section('title', __('member.login.title'))
@section('description', __('member.login.description'))
@section('heading', __('member.login.heading'))

@section('member_body')
    @php($s = app()->getLocale() === 'en' ? '.en' : '')
    <form method="POST" action="{{ route('member.login.store'.$s) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <h2 class="text-xl font-bold text-moss">{{ __('member.login.password_heading') }}</h2>
        <x-member-field name="email" type="email" :label="__('member.fields.email')" autocomplete="email" required />
        <x-member-field name="password" type="password" :label="__('member.fields.password')" autocomplete="current-password" required />
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="size-4 accent-moss">
            {{ __('member.login.remember') }}
        </label>
        <div class="flex items-center justify-between gap-4">
            <x-member-submit>{{ __('member.login.submit') }}</x-member-submit>
            <a href="{{ route('member.password.request'.$s) }}" class="text-sm font-medium text-moss underline underline-offset-4">{{ __('member.login.forgot') }}</a>
        </div>
    </form>

    <form method="POST" action="{{ route('member.login.link.request'.$s) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <h2 class="text-xl font-bold text-moss">{{ __('member.login.link_heading') }}</h2>
        <p class="text-sm leading-relaxed text-ink/80">{{ __('member.login.link_intro') }}</p>
        <x-member-field name="email" type="email" :label="__('member.fields.email')" autocomplete="email" required />
        <x-member-submit>{{ __('member.login.link_submit') }}</x-member-submit>
    </form>

    <p class="mt-6 text-sm">
        {{ __('member.login.register_prompt') }}
        <a href="{{ route('member.register'.$s) }}" class="font-semibold text-moss underline underline-offset-4">{{ __('member.login.register_link') }}</a>
    </p>
@endsection
```

`resources/views/member/center.blade.php`：

```blade
@extends('member.layout')

@section('route', 'member.center')
@section('robots', 'noindex')
@section('title', __('member.center.title'))
@section('description', __('member.center.title'))
@section('heading', __('member.center.heading'))

@section('member_body')
    <div class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        <p class="text-lg font-semibold">{{ __('member.center.welcome', ['name' => $member->name]) }}</p>
        <dl class="space-y-2 text-sm">
            <div>
                <dt class="font-semibold">{{ __('member.fields.email') }}</dt>
                <dd class="text-ink/80">{{ $member->email }}</dd>
            </div>
            <div>
                <dt class="font-semibold">{{ __('member.center.organization') }}</dt>
                <dd class="text-ink/80">{{ $member->organization ?? __('member.center.not_set') }}</dd>
            </div>
        </dl>
        <form method="POST" action="{{ route('member.logout'.(app()->getLocale() === 'en' ? '.en' : '')) }}">
            @csrf
            <x-member-submit>{{ __('member.center.logout') }}</x-member-submit>
        </form>
    </div>
@endsection
```

- [ ] **Step 6: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/LoginTest.php`
Expected: PASS，12 個測試。

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources lang tests/Feature/Member/LoginTest.php
git commit -m "Add member password login, logout and center page"
```

---

### Task 7: 登入連結的要求與使用

**Files:**
- Modify: `app/Http/Controllers/Member/LoginLinkController.php`
- Test: `tests/Feature/Member/LoginLinkTest.php`

**Interfaces:**
- Consumes: Task 1 的 `issueFor`、`consume`、`throttleKey`、`HOURLY_LIMIT`；Task 3 的 `MemberLoginLinkNotification`；Task 6 的登入頁。
- Produces: `POST /login/link`、`GET /login/link/{token}` 與英文版。

- [ ] **Step 1: 寫失敗的測試**

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

class LoginLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function sentUrl(Member $member): string
    {
        $url = null;

        Notification::assertSentTo($member, MemberLoginLinkNotification::class, function ($notification) use (&$url) {
            $url = $notification->url;

            return true;
        });

        return $url;
    }

    public function test_requesting_a_link_emails_a_one_time_link_to_an_active_member(): void
    {
        Notification::fake();
        $member = Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/login/link', ['email' => 'm@example.com'])
            ->assertRedirect()
            ->assertSessionHas('link_status');

        $this->assertStringStartsWith(url('/login/link/'), $this->sentUrl($member));
        $this->assertSame(1, $member->loginLinks()->count());
    }

    public function test_english_request_emails_an_english_link(): void
    {
        Notification::fake();
        $member = Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/en/login/link', ['email' => 'm@example.com']);

        $this->assertStringStartsWith(url('/en/login/link/'), $this->sentUrl($member));
    }

    public function test_known_and_unknown_emails_get_the_same_response_and_unknown_gets_no_mail(): void
    {
        Notification::fake();
        Member::factory()->create(['email' => 'known@example.com']);

        $known = $this->post('/login/link', ['email' => 'known@example.com']);
        $unknown = $this->post('/login/link', ['email' => 'ghost@example.com']);

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame(session('link_status'), __('member.login.link_sent'));
        Notification::assertSentTimes(MemberLoginLinkNotification::class, 1);
        $this->assertDatabaseCount('member_login_links', 1);
    }

    public function test_inactive_members_get_no_link(): void
    {
        Notification::fake();
        Member::factory()->inactive()->create(['email' => 'off@example.com']);

        $this->post('/login/link', ['email' => 'off@example.com'])->assertSessionHas('link_status');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('member_login_links', 0);
    }

    public function test_email_is_matched_case_insensitively(): void
    {
        Notification::fake();
        $member = Member::factory()->create(['email' => 'alice@example.com']);

        $this->post('/login/link', ['email' => 'Alice@Example.COM']);

        $this->assertNotEmpty($this->sentUrl($member));
    }

    public function test_the_emailed_link_logs_the_member_in_once(): void
    {
        $member = Member::factory()->create();
        $token = MemberLoginLink::issueFor($member);

        $this->get('/login/link/'.$token)->assertRedirect('/member');
        $this->assertAuthenticatedAs($member, 'member');

        auth('member')->logout();
        $this->get('/login/link/'.$token)->assertRedirect('/login')->assertSessionHasErrors('link');
        $this->assertGuest('member');
    }

    public function test_english_link_redirects_to_the_english_center(): void
    {
        $member = Member::factory()->create();

        $this->get('/en/login/link/'.MemberLoginLink::issueFor($member))->assertRedirect('/en/member');
    }

    public function test_using_a_link_verifies_an_unverified_email(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->get('/login/link/'.MemberLoginLink::issueFor($member));

        $this->assertNotNull($member->fresh()->email_verified_at);
    }

    public function test_invalid_expired_revoked_and_inactive_links_do_not_log_in(): void
    {
        $this->get('/login/link/not-a-real-token')->assertRedirect('/login');
        $this->assertGuest('member');

        $expired = Member::factory()->create();
        $expiredToken = MemberLoginLink::issueFor($expired);
        $expired->loginLinks()->update(['expires_at' => now()->subMinute()]);
        $this->get('/login/link/'.$expiredToken)->assertRedirect('/login');

        $inactive = Member::factory()->inactive()->create();
        $inactiveToken = MemberLoginLink::issueFor($inactive);
        $this->get('/login/link/'.$inactiveToken)->assertRedirect('/login');

        $this->assertGuest('member');
    }

    public function test_a_newer_link_invalidates_the_older_one(): void
    {
        $member = Member::factory()->create();
        $old = MemberLoginLink::issueFor($member);
        MemberLoginLink::issueFor($member);

        $this->get('/login/link/'.$old)->assertRedirect('/login');
        $this->assertGuest('member');
    }

    public function test_more_than_five_requests_per_hour_per_email_return_429(): void
    {
        Notification::fake();
        Member::factory()->create(['email' => 'm@example.com']);
        RateLimiter::clear(MemberLoginLink::throttleKey('m@example.com'));

        for ($i = 0; $i < MemberLoginLink::HOURLY_LIMIT; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$i])
                ->post('/login/link', ['email' => 'm@example.com'])
                ->assertSessionHas('link_status');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post('/login/link', ['email' => 'M@example.com'])
            ->assertStatus(429);
    }

    public function test_unknown_emails_are_throttled_the_same_way(): void
    {
        for ($i = 0; $i < MemberLoginLink::HOURLY_LIMIT; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.'.$i])->post('/login/link', ['email' => 'ghost@example.com']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.99'])
            ->post('/login/link', ['email' => 'ghost@example.com'])
            ->assertStatus(429);
    }

    public function test_request_requires_a_valid_email(): void
    {
        $this->post('/login/link', ['email' => 'nope'])->assertSessionHasErrors('email');
    }
}
```

- [ ] **Step 2: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/LoginLinkTest.php`
Expected: FAIL，控制器方法不存在。

- [ ] **Step 3: 實作控制器**

`app/Http/Controllers/Member/LoginLinkController.php`：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Notifications\MemberLoginLinkNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginLinkController extends MemberController
{
    public function request(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);
        $email = Str::lower(trim($data['email']));

        $key = MemberLoginLink::throttleKey($email);

        abort_if(RateLimiter::tooManyAttempts($key, MemberLoginLink::HOURLY_LIMIT), 429);

        RateLimiter::hit($key, 3600);

        $member = Member::query()->where('email', $email)->where('is_active', true)->first();

        if ($member !== null) {
            $token = MemberLoginLink::issueFor($member);

            $member->notify(
                (new MemberLoginLinkNotification($this->localizedRoute('member.login.link.consume', ['token' => $token])))
                    ->locale(app()->getLocale()),
            );
        }

        return back()->with('link_status', __('member.login.link_sent'));
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        $member = MemberLoginLink::consume($token);

        if ($member === null) {
            return redirect($this->localizedRoute('member.login'))
                ->withErrors(['link' => __('member.errors.link_invalid')]);
        }

        if (! $member->hasVerifiedEmail()) {
            $member->markEmailAsVerified();
            event(new Verified($member));
        }

        $this->guard()->login($member);
        $request->session()->regenerate();

        return redirect($this->localizedRoute('member.center'));
    }
}
```

- [ ] **Step 4: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/LoginLinkTest.php`
Expected: PASS，12 個測試。`test_known_and_unknown_emails...` 內的 `session('link_status')` 取最後一次請求的 session，若失敗改用 `->assertSessionHas('link_status', __('member.login.link_sent'))` 分別斷言兩次回應，並以 `Ruling:` 記錄。

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app tests/Feature/Member/LoginLinkTest.php
git commit -m "Add member login link request and consume"
```

---

### Task 8: Email 驗證流程

**Files:**
- Modify: `app/Http/Controllers/Member/EmailVerificationController.php`、`lang/zh_TW/member.php`、`lang/en/member.php`
- Create: `resources/views/member/verify-notice.blade.php`
- Test: `tests/Feature/Member/EmailVerificationTest.php`

**Interfaces:**
- Consumes: Task 3 的 `VerifyMemberEmailNotification`、Task 4 的路由。
- Produces: 驗證提示頁、重寄、簽章驗證連結處理。

- [ ] **Step 1: 文案**

`lang/zh_TW/member.php` 新增：

```php
'verify' => [
    'title' => '驗證 Email | 共聚年會 GatherConf 2026',
    'heading' => '請驗證你的 Email',
    'body' => '我們已寄出驗證信，請到信箱點擊連結完成驗證。',
    'resend' => '重新寄送驗證信',
    'resent' => '驗證信已重新寄出。',
    'done' => 'Email 已驗證完成。',
],
```

`lang/en/member.php` 新增：

```php
'verify' => [
    'title' => 'Verify your email | GatherConf 2026',
    'heading' => 'Please verify your email',
    'body' => 'We have sent you a verification email. Click the link in it to finish.',
    'resend' => 'Resend verification email',
    'resent' => 'The verification email has been sent again.',
    'done' => 'Your email has been verified.',
],
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Notifications\VerifyMemberEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function signedUrl(Member $member, string $name = 'member.verification.verify', ?string $hash = null): string
    {
        return URL::temporarySignedRoute($name, now()->addMinutes(60), [
            'id' => $member->id,
            'hash' => $hash ?? sha1($member->email),
        ]);
    }

    public function test_notice_page_renders_for_unverified_members(): void
    {
        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/email/verify')
            ->assertOk()
            ->assertSee('請驗證你的 Email')
            ->assertSee('noindex', false);

        $this->actingAs(Member::factory()->unverified()->create(), 'member')
            ->get('/en/email/verify')
            ->assertOk()
            ->assertSee('Please verify your email');
    }

    public function test_notice_page_requires_login(): void
    {
        $this->get('/email/verify')->assertRedirect('/login');
    }

    public function test_verified_members_are_sent_on_to_the_center(): void
    {
        $this->actingAs(Member::factory()->create(), 'member')->get('/email/verify')->assertRedirect('/member');
    }

    public function test_a_valid_signed_link_verifies_the_email_even_when_logged_out(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->get($this->signedUrl($member))->assertRedirect('/login');

        $this->assertNotNull($member->fresh()->email_verified_at);
    }

    public function test_a_logged_in_member_is_sent_to_the_center_after_verifying(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->actingAs($member, 'member')->get($this->signedUrl($member))->assertRedirect('/member');
    }

    public function test_english_link_redirects_to_english_pages(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->get($this->signedUrl($member, 'member.verification.verify.en'))->assertRedirect('/en/login');
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->get($this->signedUrl($member).'x')->assertForbidden();

        $this->assertNull($member->fresh()->email_verified_at);
    }

    public function test_a_wrong_hash_is_rejected(): void
    {
        $member = Member::factory()->unverified()->create();

        $this->get($this->signedUrl($member, hash: sha1('someone-else@example.com')))->assertForbidden();

        $this->assertNull($member->fresh()->email_verified_at);
    }

    public function test_an_unknown_member_returns_404(): void
    {
        $url = URL::temporarySignedRoute('member.verification.verify', now()->addMinutes(60), ['id' => 999999, 'hash' => 'x']);

        $this->get($url)->assertNotFound();
    }

    public function test_verifying_twice_is_harmless(): void
    {
        $member = Member::factory()->unverified()->create();
        $url = $this->signedUrl($member);

        $this->get($url);
        $first = $member->fresh()->email_verified_at;
        $this->get($url)->assertRedirect();

        $this->assertEquals($first, $member->fresh()->email_verified_at);
    }

    public function test_resend_sends_the_notification_and_is_rate_limited(): void
    {
        Notification::fake();
        $member = Member::factory()->unverified()->create();

        $this->actingAs($member, 'member')->post('/email/verification-notification')->assertSessionHas('status');
        Notification::assertSentTo($member, VerifyMemberEmailNotification::class);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/email/verification-notification');
        }

        $this->post('/email/verification-notification')->assertStatus(429);
    }

    public function test_resend_is_a_no_op_for_verified_members(): void
    {
        Notification::fake();
        $member = Member::factory()->create();

        $this->actingAs($member, 'member')->post('/email/verification-notification')->assertRedirect('/member');

        Notification::assertNothingSent();
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/EmailVerificationTest.php`
Expected: FAIL，控制器方法不存在。

- [ ] **Step 4: 實作控制器**

`app/Http/Controllers/Member/EmailVerificationController.php`：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends MemberController
{
    public function notice(): View|RedirectResponse
    {
        if ($this->guard()->user()->hasVerifiedEmail()) {
            return redirect($this->localizedRoute('member.center'));
        }

        return view('member.verify-notice');
    }

    public function send(Request $request): RedirectResponse
    {
        $member = $this->guard()->user();

        if ($member->hasVerifiedEmail()) {
            return redirect($this->localizedRoute('member.center'));
        }

        $member->sendEmailVerificationNotification();

        return back()->with('status', __('member.verify.resent'));
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $member = Member::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($member->getEmailForVerification()), $hash), 403);

        if (! $member->hasVerifiedEmail()) {
            $member->markEmailAsVerified();
            event(new Verified($member));
        }

        $destination = $this->guard()->check() ? 'member.center' : 'member.login';

        return redirect($this->localizedRoute($destination))->with('status', __('member.verify.done'));
    }
}
```

- [ ] **Step 5: 實作頁面**

`resources/views/member/verify-notice.blade.php`：

```blade
@extends('member.layout')

@section('route', 'member.verification.notice')
@section('robots', 'noindex')
@section('title', __('member.verify.title'))
@section('description', __('member.verify.title'))
@section('heading', __('member.verify.heading'))
@section('intro', __('member.verify.body'))

@section('member_body')
    <form method="POST" action="{{ route('member.verification.send'.(app()->getLocale() === 'en' ? '.en' : '')) }}" class="mt-8">
        @csrf
        <x-member-submit>{{ __('member.verify.resend') }}</x-member-submit>
    </form>
@endsection
```

- [ ] **Step 6: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/EmailVerificationTest.php`
Expected: PASS，11 個測試。

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources lang tests/Feature/Member/EmailVerificationTest.php
git commit -m "Add member email verification flow"
```

---

### Task 9: 忘記密碼與重設密碼

**Files:**
- Modify: `app/Http/Controllers/Member/PasswordResetController.php`、`lang/zh_TW/member.php`、`lang/en/member.php`
- Create: `resources/views/member/forgot-password.blade.php`、`resources/views/member/reset-password.blade.php`
- Test: `tests/Feature/Member/PasswordResetTest.php`

**Interfaces:**
- Consumes: Task 3 的 `ResetMemberPasswordNotification`、Task 2 的 `members` password broker。
- Produces: 忘記密碼與重設密碼頁面與處理。

- [ ] **Step 1: 文案**

`lang/zh_TW/member.php` 新增：

```php
'forgot' => [
    'title' => '忘記密碼 | 共聚年會 GatherConf 2026',
    'heading' => '忘記密碼',
    'intro' => '輸入你的 Email，我們會寄一封重設密碼的信給你。',
    'submit' => '寄送重設連結',
    'sent' => '如果這個 Email 已註冊，重設密碼的信已經寄出，請到信箱查看。',
],
'reset' => [
    'title' => '重設密碼 | 共聚年會 GatherConf 2026',
    'heading' => '重設密碼',
    'submit' => '重設密碼',
    'done' => '密碼已重設，請重新登入。',
    'invalid' => '這個重設連結已失效，請重新要求。',
],
```

`lang/en/member.php` 新增：

```php
'forgot' => [
    'title' => 'Forgot password | GatherConf 2026',
    'heading' => 'Forgot your password',
    'intro' => 'Enter your email and we will send you a link to reset your password.',
    'submit' => 'Send reset link',
    'sent' => 'If this email is registered, a reset email has been sent. Please check your inbox.',
],
'reset' => [
    'title' => 'Reset password | GatherConf 2026',
    'heading' => 'Reset your password',
    'submit' => 'Reset password',
    'done' => 'Your password has been reset. Please log in again.',
    'invalid' => 'This reset link is no longer valid. Please request a new one.',
],
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Notifications\ResetMemberPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_pages_render_in_both_languages(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('忘記密碼')->assertSee('noindex', false);
        $this->get('/en/forgot-password')->assertOk()->assertSee('Forgot your password');
        $this->get('/reset-password/tok?email=a@example.com')->assertOk()->assertSee('重設密碼');
        $this->get('/en/reset-password/tok?email=a@example.com')->assertOk()->assertSee('Reset your password');
    }

    public function test_requesting_a_reset_emails_a_registered_member(): void
    {
        Notification::fake();
        $member = Member::factory()->create(['email' => 'm@example.com']);

        $this->post('/forgot-password', ['email' => 'm@example.com'])->assertSessionHas('status', __('member.forgot.sent'));

        Notification::assertSentTo($member, ResetMemberPasswordNotification::class);
    }

    public function test_unknown_email_gets_the_same_response_and_no_mail(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'ghost@example.com'])->assertSessionHas('status', __('member.forgot.sent'));

        Notification::assertNothingSent();
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'm@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/login')->assertSessionHas('status', __('member.reset.done'));

        $this->assertTrue(Hash::check('brand-new-pass', $member->fresh()->password));
    }

    public function test_english_reset_redirects_to_the_english_login(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/en/reset-password', [
            'token' => $token,
            'email' => 'm@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/en/login');
    }

    public function test_an_invalid_token_does_not_change_the_password(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $original = $member->password;

        $this->post('/reset-password', [
            'token' => 'wrong',
            'email' => 'm@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('email');

        $this->assertSame($original, $member->fresh()->password);
    }

    public function test_a_token_cannot_be_reused(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $token = Password::broker('members')->createToken($member);
        $payload = ['token' => $token, 'email' => 'm@example.com', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'];

        $this->post('/reset-password', $payload)->assertRedirect('/login');
        $this->post('/reset-password', array_merge($payload, ['password' => 'another-pass', 'password_confirmation' => 'another-pass']))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('brand-new-pass', $member->fresh()->password));
    }

    public function test_a_token_for_an_admin_user_does_not_work_for_a_member_with_the_same_email(): void
    {
        $user = \App\Models\User::factory()->create(['email' => 'same@example.com']);
        $member = Member::factory()->create(['email' => 'same@example.com']);
        $userToken = Password::broker('users')->createToken($user);

        $this->post('/reset-password', [
            'token' => $userToken,
            'email' => 'same@example.com',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $member->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed_and_long_enough(): void
    {
        $member = Member::factory()->create(['email' => 'm@example.com']);
        $token = Password::broker('members')->createToken($member);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'm@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/PasswordResetTest.php`
Expected: FAIL，控制器方法不存在。

- [ ] **Step 4: 實作控制器**

`app/Http/Controllers/Member/PasswordResetController.php`：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends MemberController
{
    public function create(): View
    {
        return view('member.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);

        Password::broker('members')->sendResetLink(['email' => Str::lower(trim($data['email']))]);

        return back()->with('status', __('member.forgot.sent'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('member.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::broker('members')->reset(
            [
                'email' => Str::lower(trim($data['email'])),
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (Member $member, string $password): void {
                $member->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $member->save();

                event(new PasswordReset($member));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('member.reset.invalid')]);
        }

        return redirect($this->localizedRoute('member.login'))->with('status', __('member.reset.done'));
    }
}
```

- [ ] **Step 5: 實作頁面**

`resources/views/member/forgot-password.blade.php`：

```blade
@extends('member.layout')

@section('route', 'member.password.request')
@section('robots', 'noindex')
@section('title', __('member.forgot.title'))
@section('description', __('member.forgot.title'))
@section('heading', __('member.forgot.heading'))
@section('intro', __('member.forgot.intro'))

@section('member_body')
    <form method="POST" action="{{ route('member.password.email'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <x-member-field name="email" type="email" :label="__('member.fields.email')" autocomplete="email" required />
        <x-member-submit>{{ __('member.forgot.submit') }}</x-member-submit>
    </form>
@endsection
```

`resources/views/member/reset-password.blade.php`，因網址含 token，必須提供 `zh_url` 與 `en_url`：

```blade
@extends('member.layout')

@php
    $query = ['email' => $email];
    $zhResetUrl = route('member.password.reset', ['token' => $token] + $query);
    $enResetUrl = route('member.password.reset.en', ['token' => $token] + $query);
@endphp

@section('route', 'member.password.request')
@section('zh_url', $zhResetUrl)
@section('en_url', $enResetUrl)
@section('robots', 'noindex')
@section('title', __('member.reset.title'))
@section('description', __('member.reset.title'))
@section('heading', __('member.reset.heading'))

@section('member_body')
    <form method="POST" action="{{ route('member.password.update'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-member-field name="email" type="email" :label="__('member.fields.email')" :value="$email" autocomplete="email" required />
        <x-member-field name="password" type="password" :label="__('member.fields.password')" autocomplete="new-password" required />
        <x-member-field name="password_confirmation" type="password" :label="__('member.fields.password_confirmation')" autocomplete="new-password" required />
        <x-member-submit>{{ __('member.reset.submit') }}</x-member-submit>
    </form>
@endsection
```

- [ ] **Step 6: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/PasswordResetTest.php`
Expected: PASS，9 個測試。

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources lang tests/Feature/Member/PasswordResetTest.php
git commit -m "Add member forgot and reset password"
```

---

### Task 10: 後台「寄送登入連結」動作

**Files:**
- Modify: `app/Filament/Resources/Members/Actions/MemberActions.php`、`Tables/MembersTable.php`、`Pages/ViewMember.php`、`Pages/EditMember.php`
- Test: `tests/Feature/Member/SendLoginLinkActionTest.php`

**Interfaces:**
- Consumes: Task 1 的 `issueFor`、`throttleKey`、`HOURLY_LIMIT`；Task 3 的 `MemberLoginLinkNotification`。
- Produces: `MemberActions::sendLoginLink(): Action`，動作名稱 `sendLoginLink`。

- [ ] **Step 1: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Models\Member;
use App\Models\MemberLoginLink;
use App\Models\User;
use App\Notifications\MemberLoginLinkNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class SendLoginLinkActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_action_emails_a_link_to_the_member_and_revokes_the_old_one(): void
    {
        Notification::fake();
        $member = Member::factory()->create();
        $oldToken = MemberLoginLink::issueFor($member);

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('sendLoginLink')->table($member))
            ->assertNotified('已寄送登入連結');

        Notification::assertSentTo($member, MemberLoginLinkNotification::class);
        $this->assertNull(MemberLoginLink::consume($oldToken));
        $this->assertSame(2, $member->loginLinks()->count());
    }

    public function test_the_admin_never_sees_the_link(): void
    {
        Notification::fake();
        $member = Member::factory()->create();

        $component = Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction('sendLoginLink');

        $url = null;
        Notification::assertSentTo($member, MemberLoginLinkNotification::class, function ($n) use (&$url) {
            $url = $n->url;

            return true;
        });

        $component->assertDontSee($url);
    }

    public function test_action_is_hidden_for_inactive_members(): void
    {
        $inactive = Member::factory()->inactive()->create();

        Livewire::test(ListMembers::class)
            ->assertActionHidden(TestAction::make('sendLoginLink')->table($inactive));
    }

    public function test_action_respects_the_shared_hourly_limit(): void
    {
        Notification::fake();
        $member = Member::factory()->create();
        $key = MemberLoginLink::throttleKey($member->email);

        for ($i = 0; $i < MemberLoginLink::HOURLY_LIMIT; $i++) {
            RateLimiter::hit($key, 3600);
        }

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('sendLoginLink')->table($member))
            ->assertNotified('寄送過於頻繁');

        Notification::assertNothingSent();
        $this->assertSame(0, $member->loginLinks()->count());
    }

    public function test_sending_counts_toward_the_front_end_limit(): void
    {
        Notification::fake();
        $member = Member::factory()->create();

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('sendLoginLink')->table($member));

        $this->assertSame(
            MemberLoginLink::HOURLY_LIMIT - 1,
            RateLimiter::remaining(MemberLoginLink::throttleKey($member->email), MemberLoginLink::HOURLY_LIMIT),
        );
    }
}
```

- [ ] **Step 2: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/SendLoginLinkActionTest.php`
Expected: FAIL，找不到動作 `sendLoginLink`。

- [ ] **Step 3: 實作動作**

在 `MemberActions` 加入，並補上 `use App\Models\MemberLoginLink;`、`use App\Notifications\MemberLoginLinkNotification;`、`use Illuminate\Support\Facades\RateLimiter;`：

```php
public static function sendLoginLink(): Action
{
    return Action::make('sendLoginLink')
        ->label('寄送登入連結')
        ->icon(Heroicon::OutlinedEnvelope)
        ->requiresConfirmation()
        ->modalHeading('寄送登入連結')
        ->modalDescription('將一封一次性登入連結寄到會員自己的 Email，並作廢先前尚未使用的連結。你不會看到連結內容。')
        ->visible(fn (Member $record): bool => $record->is_active)
        ->action(function (Member $record): void {
            $key = MemberLoginLink::throttleKey($record->email);

            if (RateLimiter::tooManyAttempts($key, MemberLoginLink::HOURLY_LIMIT)) {
                Notification::make()
                    ->title('寄送過於頻繁')
                    ->body('此會員一小時內已要求過多次登入連結，請稍後再試。')
                    ->warning()
                    ->send();

                return;
            }

            RateLimiter::hit($key, 3600);

            $token = MemberLoginLink::issueFor($record);

            $record->notify(
                (new MemberLoginLinkNotification(route('member.login.link.consume', ['token' => $token])))->locale('zh_TW'),
            );

            Notification::make()->title('已寄送登入連結')->success()->send();
        });
}
```

- [ ] **Step 4: 掛到列表與頁面**

`MembersTable` 的 `recordActions` 在 `markVerified()` 後加入 `MemberActions::sendLoginLink(),`。`ViewMember` 與 `EditMember` 的 `getHeaderActions()` 在 `markVerified()` 後加入 `MemberActions::sendLoginLink(),`。

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/SendLoginLinkActionTest.php tests/Feature/Member/MemberActionsTest.php`
Expected: PASS，新舊動作測試都通過。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app tests/Feature/Member/SendLoginLinkActionTest.php
git commit -m "Add admin send-login-link action"
```

---

### Task 11: 清除過期登入連結的排程

**Files:**
- Create: `app/Console/Commands/PruneMemberLoginLinks.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Member/PruneMemberLoginLinksTest.php`

**Interfaces:**
- Consumes: `MemberLoginLink`。
- Produces: 指令 `member-login-links:prune`，每日排程，刪除已使用、已作廢或已過期超過 30 天的紀錄，保留最近 30 天供稽核。

- [ ] **Step 1: 產生檔案**

```bash
php artisan make:command PruneMemberLoginLinks --command=member-login-links:prune --no-interaction
php artisan make:test Member/PruneMemberLoginLinksTest --phpunit --no-interaction
```

- [ ] **Step 2: 寫失敗的測試**

```php
<?php

namespace Tests\Feature\Member;

use App\Models\MemberLoginLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PruneMemberLoginLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_old_used_revoked_and_expired_links_only(): void
    {
        $oldUsed = MemberLoginLink::factory()->create(['used_at' => now()->subDays(31)]);
        $oldRevoked = MemberLoginLink::factory()->create(['revoked_at' => now()->subDays(31)]);
        $oldExpired = MemberLoginLink::factory()->create(['expires_at' => now()->subDays(31)]);

        $recentUsed = MemberLoginLink::factory()->create(['used_at' => now()->subDays(29)]);
        $recentExpired = MemberLoginLink::factory()->create(['expires_at' => now()->subDays(29)]);
        $usable = MemberLoginLink::factory()->create();

        $this->artisan('member-login-links:prune')->assertSuccessful();

        $this->assertModelMissing($oldUsed);
        $this->assertModelMissing($oldRevoked);
        $this->assertModelMissing($oldExpired);
        $this->assertModelExists($recentUsed);
        $this->assertModelExists($recentExpired);
        $this->assertModelExists($usable);
    }

    public function test_it_runs_daily(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('member-login-links:prune', Artisan::output());
    }
}
```

- [ ] **Step 3: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/PruneMemberLoginLinksTest.php`
Expected: FAIL，指令不存在。

- [ ] **Step 4: 實作指令與排程**

`app/Console/Commands/PruneMemberLoginLinks.php` 完整取代：

```php
<?php

namespace App\Console\Commands;

use App\Models\MemberLoginLink;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;

#[AsCommand(name: 'member-login-links:prune', description: 'Delete login link records that ended more than 30 days ago')]
class PruneMemberLoginLinks extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(30);

        $deleted = MemberLoginLink::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where('used_at', '<', $cutoff)
                    ->orWhere('revoked_at', '<', $cutoff)
                    ->orWhere('expires_at', '<', $cutoff);
            })
            ->delete();

        $this->info("Deleted {$deleted} login link records.");

        return self::SUCCESS;
    }
}
```

`routes/console.php` 末端加入，並 `use Illuminate\Support\Facades\Schedule;`：

```php
Schedule::command('member-login-links:prune')->daily();
```

- [ ] **Step 5: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/PruneMemberLoginLinksTest.php`
Expected: PASS，2 個測試。若 `AsCommand` 屬性在目前 Laravel 版本不存在，改用 `protected $signature` 與 `protected $description`，並以 `Ruling:` 記錄。

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app routes tests/Feature/Member/PruneMemberLoginLinksTest.php
git commit -m "Add scheduled pruning of old member login links"
```

---

### Task 12: 入口、robots、清理與整體驗證

**Files:**
- Modify: `resources/views/landing/footer.blade.php`、`lang/zh_TW/landing.php`、`lang/en/landing.php`、`routes/web.php`、`tests/Feature/ExampleTest.php`、`app/Filament/Resources/Members/Pages/ListMembers.php`
- Test: `tests/Feature/Member/MemberEntryPointsTest.php`

**Interfaces:**
- Consumes: 全部前面任務。
- Produces: 首頁頁尾的會員登入連結，`robots.txt` 禁止索引會員頁面。

- [ ] **Step 1: 寫失敗的測試**

```bash
php artisan make:test Member/MemberEntryPointsTest --phpunit --no-interaction
```

```php
<?php

namespace Tests\Feature\Member;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberEntryPointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_landing_footer_links_to_member_login_in_both_languages(): void
    {
        $this->get('/')->assertSee(route('member.login'), false)->assertSee('會員登入');
        $this->get('/en')->assertSee(route('member.login.en'), false)->assertSee('Member login');
    }

    public function test_robots_txt_blocks_member_pages(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['/member', '/login', '/register', '/forgot-password', '/reset-password', '/email/verify', '/en/member', '/en/login', '/en/register'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $robots);
        }
    }

    public function test_member_pages_are_not_in_the_sitemap(): void
    {
        $this->get('/sitemap.xml')->assertDontSee('/login')->assertDontSee('/register');
    }

    public function test_member_pages_are_marked_noindex(): void
    {
        foreach (['/login', '/en/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)->assertSee('<meta name="robots" content="noindex">', false);
        }
    }
}
```

- [ ] **Step 2: 執行測試，確認失敗**

Run: `php artisan test --compact tests/Feature/Member/MemberEntryPointsTest.php`
Expected: FAIL，頁尾沒有連結，robots 沒有規則。

- [ ] **Step 3: 實作**

`lang/zh_TW/landing.php` 加入 `'member_login_link' => '會員登入',`，`lang/en/landing.php` 加入 `'member_login_link' => 'Member login',`。

`resources/views/landing/footer.blade.php` 在社群連結之後加入：

```blade
<a href="{{ app()->getLocale() === 'en' ? route('member.login.en') : route('member.login') }}" class="text-gold underline">{{ __('landing.member_login_link') }}</a>
```

`routes/web.php` 的 robots 路由，把回應字串改為：

```php
$disallow = collect(['/admin', '/member', '/login', '/register', '/forgot-password', '/reset-password', '/email/verify', '/en/member', '/en/login', '/en/register', '/en/forgot-password', '/en/reset-password', '/en/email/verify'])
    ->map(fn (string $path) => 'Disallow: '.$path)
    ->implode("\n");

return response("User-agent: *\n".$disallow."\n\nSitemap: ".route('sitemap')."\n")
    ->header('Content-Type', 'text/plain');
```

`app/Filament/Resources/Members/Pages/ListMembers.php` 的 `getHeaderActions()` 保留回傳空陣列，這是 Filament 預期的覆寫，若檔案只剩空方法，移除該方法與未使用的 `use`。

- [ ] **Step 4: 執行測試，確認通過**

Run: `php artisan test --compact tests/Feature/Member/MemberEntryPointsTest.php tests/Feature/ExampleTest.php`
Expected: PASS，既有的 `Disallow: /admin` 斷言仍通過。

- [ ] **Step 5: 完整驗證**

Run: `php artisan test --compact`
Expected: 全部 PASS。

- [ ] **Step 6: 手動確認**

```bash
php artisan migrate --no-interaction
npm run build
```

在瀏覽器開啟 `http://localhost:8000/register`，確認：

- 註冊、登入、登出可以完成，英文頁面全為英文。
- 開發環境信件寫入 `storage/logs/laravel.log`，取出登入連結並開啟，可以登入。
- 後台會員頁的「寄送登入連結」可用，且畫面看不到連結。
- 頁面為 light 與 dark 兩種模式都可讀。

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources lang routes app tests
git commit -m "Add member login entry point, robots rules and cleanup"
```

---

## 不在本計畫內

- 會員個人資料編輯、變更 Email 與變更密碼頁。
- 社群登入、雙重認證。
- 連結點擊前的確認頁，以防郵件掃描器預先消耗連結，見計畫決定第 5 項。
- 部署設定，包含 `MAILGUN_DOMAIN`、`MAILGUN_SECRET`、`MAIL_FROM_ADDRESS`、`APP_NAME` 與排程器的 cron。
- 讓 `User` 實作 `FilamentUser`，這是既有狀況。
