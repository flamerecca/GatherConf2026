# 會員發訊息給合作社群 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans, 此專案一律用 Native 執行。Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 會員在合作社群頁選一個社群送出訊息，訊息進後台「社群訊息」收件匣，由管理員標記處理狀態與備註，會員看得到自己訊息的狀態。

**Architecture:** 社群仍是 `config/communities.php` 的靜態資料，每筆新增固定 `slug`，訊息只存 `community_slug` 與送出當下的名稱快照。前台沿用既有的會員路由群組與雙語路由，後台新增唯讀內容、可標記狀態與備註的 Filament 資源。

**Tech Stack:** Laravel 13、PHP 8.5、Filament 4、PHPUnit 12，沿用 UUIDv7 與既有會員系統。

**Spec:** `docs/superpowers/specs/2026-10-15-community-messages-design.md`

## Global Constraints

- 主鍵 `HasUuids`，外鍵 `foreignUuid`，只新增 migration，不改既有 migration。
- 模型用 `#[Fillable([...])]` Attribute，不用 `protected $fillable`，見 `.ai/rules/models.md`。
- 路徑放在 `/member` 底下，使 `robots.txt` 既有前綴涵蓋，路由名稱 `member.community-message...` 與 `.en`。
- 前台頁面 `noindex`，繁體中文與英文各一組文案，放 `lang/{zh_TW,en}/community-messages.php`。
- 確認信不含主旨與內文，語系固定 `zh_TW`，寄信失敗只記錄，用 `MemberController::sendQuietly`。
- 後台文字繁體中文，導覽群組「會員」，不提供新增、編輯主旨與內文、刪除。
- 每個 PHP 修改後執行 `vendor/bin/pint --dirty --format agent`，測試用 factory，若 Pint 改到不相關檔案就還原。
- 測試環境預設語系是 `en`，中文頁面測試使用 `route('...')` 的中文路由，並以 `Auth::forgetGuards()` 切換會員。
- 不新增依賴，不新增 `app/` 底下的基底資料夾，輔助類別放 `app/Models/`。

## 決策紀錄

- `x-community-card` 整張卡片原本是一個 `<a>`，不能在裡面再放連結。改為外層 `div` 承載邊框，內層 `a` 負責原本的連結，聯絡連結放在 `div` 內且只在合作社群頁顯示。首頁區塊外觀與行為不變。
- 每日 5 則的檢查與寫入以每位會員的快取鎖串行化，與漂流瓶做法一致，避免並行請求超過上限。
- 待處理數量的導覽標籤直接查詢，會員數與訊息量很小，不加快取。

## Review Focus

- 兩個並行請求同時送出，第 6 則不會被放行。
- 訪客或未驗證會員送出時不會建立訊息，登入後可回到原頁。
- 會員只能看到自己的訊息，內文與備註不會出現在會員端頁面。
- 不存在的 `slug` 在 GET 與 POST 都回應 404，且不建立訊息。
- 後台不能修改主旨與內文，狀態變更會記錄處理人與時間。
- 首頁的社群卡片沒有聯絡連結，且仍然是單一連結。

---

## File Structure

| 檔案 | 責任 |
| :---- | :---- |
| `config/communities.php` | 每筆新增 `slug` |
| `app/Models/CommunityDirectory.php` | 依 `slug` 取得社群設定 |
| `database/migrations/*_create_community_messages_table.php` | 資料表 |
| `app/Models/CommunityMessage.php` | 狀態與處理方法 |
| `app/Http/Controllers/Member/CommunityMessageController.php` | 寫訊息、送出、我的訊息 |
| `app/Notifications/CommunityMessageReceivedNotification.php` | 確認信 |
| `resources/views/member/community-message-create.blade.php`、`community-messages.blade.php` | 前台頁面 |
| `resources/views/components/community-card.blade.php`、`communities.blade.php` | 聯絡連結 |
| `lang/zh_TW/community-messages.php`、`lang/en/community-messages.php` | 文案 |
| `app/Filament/Resources/CommunityMessages/*` | 後台資源 |
| `tests/Feature/CommunityMessages/*` | 測試 |

---

### Task 1: 社群 slug、資料表與模型

**Files:**
- Modify: `config/communities.php`
- Create: `app/Models/CommunityDirectory.php`、`app/Models/CommunityMessage.php`
- Create: `database/migrations/2026_10_15_100000_create_community_messages_table.php`
- Create: `database/factories/CommunityMessageFactory.php`
- Modify: `app/Models/Member.php`
- Test: `tests/Feature/CommunityMessages/CommunityMessageModelTest.php`

**Interfaces:**
- Produces：`CommunityDirectory::all(): array`、`CommunityDirectory::find(string $slug): ?array`、`CommunityDirectory::name(array $community, ?string $locale = null): string`
- Produces 常數：`CommunityMessage::STATUS_PENDING`、`STATUS_FORWARDED`、`STATUS_CLOSED`、`DAILY_LIMIT = 5`、`SUBJECT_LENGTH = 100`、`BODY_LENGTH = 1000`
- Produces：`CommunityMessage::sentTodayBy(Member $member): int`、`markAs(string $status, ?int $by): void`、`scopePending()`
- Produces：`Member::communityMessages(): HasMany`

- [ ] **Step 1: 為社群加入 slug**

`config/communities.php` 依序為八筆加入 `'slug' =>`，放在 `'name'` 之前：

| 社群 | slug |
| :---- | :---- |
| Taiwan Kotlin User Group | `taiwan-kotlin-user-group` |
| 網路遊民探險中 | `digital-rogue` |
| Taiwan Obsidian User Group | `taiwan-obsidian-user-group` |
| GDG 桃園 | `gdg-taoyuan` |
| 開發者 Buffet | `buffet-dev` |
| iPlayground | `iplayground` |
| 人本與科技共融發展協會 | `orbio-community` |
| WordPress Taiwan | `wordpress-taiwan` |

並更新檔頭型別註解加上 `slug: string`，說明 `slug` 一旦公開就不要更動。

- [ ] **Step 2: 寫失敗的測試**

`tests/Feature/CommunityMessages/CommunityMessageModelTest.php`：

```php
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
```

- [ ] **Step 3: 驗證失敗**

Run: `php artisan test --compact tests/Feature/CommunityMessages/CommunityMessageModelTest.php`
Expected: FAIL，類別與資料表不存在。

- [ ] **Step 4: 實作**

Migration：

```php
Schema::create('community_messages', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('member_id')->constrained('members')->cascadeOnDelete();
    $table->string('community_slug', 60);
    $table->string('community_name');
    $table->string('subject', 100);
    $table->text('body');
    $table->string('status', 20)->default('pending');
    $table->text('admin_note')->nullable();
    $table->unsignedBigInteger('handled_by')->nullable();
    $table->timestamp('handled_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'created_at']);
    $table->index(['member_id', 'created_at']);
});
```

`CommunityDirectory`：

```php
<?php

namespace App\Models;

final class CommunityDirectory
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return array_values(config('communities', []));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $community) {
            if (($community['slug'] ?? null) === $slug) {
                return $community;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $community
     */
    public static function name(array $community, ?string $locale = null): string
    {
        return $community['name'][$locale ?? app()->getLocale()] ?? $community['name']['zh_TW'];
    }
}
```

`CommunityMessage`：

```php
<?php

namespace App\Models;

use Database\Factories\CommunityMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'community_slug', 'community_name', 'subject', 'body'])]
class CommunityMessage extends Model
{
    /** @use HasFactory<CommunityMessageFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_FORWARDED = 'forwarded';

    public const STATUS_CLOSED = 'closed';

    public const DAILY_LIMIT = 5;

    public const SUBJECT_LENGTH = 100;

    public const BODY_LENGTH = 1000;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @param  Builder<CommunityMessage>  $query
     * @return Builder<CommunityMessage>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public static function sentTodayBy(Member $member): int
    {
        return static::query()
            ->where('member_id', $member->getKey())
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    public function markAs(string $status, ?int $by): void
    {
        $reopened = $status === self::STATUS_PENDING;

        $this->forceFill([
            'status' => $status,
            'handled_by' => $reopened ? null : $by,
            'handled_at' => $reopened ? null : now(),
        ])->save();
    }
}
```

`CommunityMessageFactory::definition()` 回傳 `['member_id' => Member::factory(), 'community_slug' => 'gdg-taoyuan', 'community_name' => 'GDG 桃園', 'subject' => fake()->sentence(4), 'body' => fake()->paragraph()]`。

`Member` 加入 `communityMessages(): HasMany`。

- [ ] **Step 5: 驗證通過並 Commit**

Run: `php artisan test --compact tests/Feature/CommunityMessages`
Expected: PASS。

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Add community slugs and the community message model"
```

---

### Task 2: 前台流程與確認信

**Files:**
- Create: `app/Http/Controllers/Member/CommunityMessageController.php`
- Create: `app/Notifications/CommunityMessageReceivedNotification.php`
- Create: `resources/views/member/community-message-create.blade.php`、`community-messages.blade.php`
- Create: `lang/zh_TW/community-messages.php`、`lang/en/community-messages.php`
- Modify: `routes/web.php`、`app/Providers/AppServiceProvider.php`、`resources/views/member/center.blade.php`
- Test: `tests/Feature/CommunityMessages/CommunityMessageFlowTest.php`

**Interfaces:**
- Consumes: `CommunityDirectory::find/name`、`CommunityMessage::sentTodayBy`、`DAILY_LIMIT`
- Produces: 路由 `member.community-message.create`、`member.community-message.store`、`member.community-messages` 與 `.en`，限流器 `member-community-message`

- [ ] **Step 1: 寫失敗的測試**

`CommunityMessageFlowTest`，`RefreshDatabase`，提供 `asMember()`，並在 `setUp` 清除限流。

```php
private function payload(array $override = []): array
{
    return array_merge(['subject' => '想詢問合作', 'body' => '請問有沒有講座可以一起辦？', 'agree_conduct' => '1'], $override);
}

public function test_the_form_shows_the_community_name_in_both_languages(): void
{
    $me = Member::factory()->create();

    $this->asMember($me)->get(route('member.community-message.create', 'gdg-taoyuan'))
        ->assertOk()->assertSee('GDG 桃園')->assertSee('noindex', false);
    $this->asMember($me)->get(route('member.community-message.create.en', 'gdg-taoyuan'))
        ->assertOk()->assertSee(trans('community-messages.create.heading', [], 'en'), false);
}

public function test_the_form_links_to_the_code_of_conduct(): void
{
    $this->asMember(Member::factory()->create())
        ->get(route('member.community-message.create', 'gdg-taoyuan'))
        ->assertSee(route('conduct'), false);
}

public function test_a_guest_is_sent_to_login_and_returns_to_the_form(): void
{
    $url = route('member.community-message.create', 'gdg-taoyuan');

    $this->get($url)->assertRedirect(route('member.login'));
    $this->assertSame($url, session('url.intended'));
}

public function test_unverified_and_inactive_members_cannot_send(): void
{
    $this->asMember(Member::factory()->unverified()->create())
        ->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload())
        ->assertRedirect(route('member.verification.notice'));
    $this->asMember(Member::factory()->inactive()->create())
        ->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload());

    $this->assertSame(0, CommunityMessage::query()->count());
}

public function test_an_unknown_slug_is_a_404_for_get_and_post(): void
{
    $me = Member::factory()->create();

    $this->asMember($me)->get(route('member.community-message.create', 'nope'))->assertNotFound();
    $this->asMember($me)->post(route('member.community-message.store', 'nope'), $this->payload())->assertNotFound();
    $this->assertSame(0, CommunityMessage::query()->count());
}

public function test_a_message_is_stored_trimmed_with_the_community_name(): void
{
    Notification::fake();
    $me = Member::factory()->create();

    $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload(['subject' => '  想詢問  ']))
        ->assertRedirect(route('member.community-messages'));

    $message = CommunityMessage::query()->firstOrFail();
    $this->assertSame('想詢問', $message->subject);
    $this->assertSame('GDG 桃園', $message->community_name);
    $this->assertSame('gdg-taoyuan', $message->community_slug);
    $this->assertSame('pending', $message->status);
    $this->assertTrue($message->member->is($me));
}

public function test_validation(): void
{
    $me = Member::factory()->create();
    $url = route('member.community-message.store', 'gdg-taoyuan');

    $this->asMember($me)->post($url, $this->payload(['subject' => '']))->assertSessionHasErrors('subject');
    $this->asMember($me)->post($url, $this->payload(['body' => '   ']))->assertSessionHasErrors('body');
    $this->asMember($me)->post($url, $this->payload(['subject' => str_repeat('字', 101)]))->assertSessionHasErrors('subject');
    $this->asMember($me)->post($url, $this->payload(['body' => str_repeat('字', 1001)]))->assertSessionHasErrors('body');
    $this->asMember($me)->post($url, $this->payload(['agree_conduct' => null]))->assertSessionHasErrors('agree_conduct');

    $this->assertSame(0, CommunityMessage::query()->count());
}

public function test_the_sixth_message_in_a_day_is_refused(): void
{
    $me = Member::factory()->create();
    CommunityMessage::factory()->count(5)->for($me)->create();

    $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload())
        ->assertSessionHasErrors('body');

    $this->assertSame(5, CommunityMessage::query()->count());
}

public function test_older_messages_do_not_count_against_the_limit(): void
{
    Notification::fake();
    $me = Member::factory()->create();
    CommunityMessage::factory()->count(5)->for($me)->create(['created_at' => now()->subDays(2)]);

    $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload())
        ->assertRedirect(route('member.community-messages'));
}

public function test_the_request_throttle_returns_429(): void
{
    Notification::fake();
    $me = Member::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload(['agree_conduct' => null]));
    }

    $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload())->assertStatus(429);
}

public function test_a_confirmation_email_is_sent_without_the_content(): void
{
    Notification::fake();
    $me = Member::factory()->create();

    $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload());

    Notification::assertSentTo($me, CommunityMessageReceivedNotification::class, function ($notification, $channels) use ($me) {
        $mail = $notification->toMail($me);
        $text = $mail->subject.' '.implode(' ', $mail->introLines).' '.implode(' ', $mail->outroLines);

        return str_contains($text, 'GDG 桃園')
            && ! str_contains($text, '想詢問合作')
            && ! str_contains($text, '講座');
    });
}

public function test_a_mail_failure_does_not_break_sending(): void
{
    $me = Member::factory()->create();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('smtp down'));

    $this->asMember($me)->post(route('member.community-message.store', 'gdg-taoyuan'), $this->payload())
        ->assertRedirect(route('member.community-messages'));

    $this->assertSame(1, CommunityMessage::query()->count());
}

public function test_the_member_list_shows_only_own_messages_without_body_or_note(): void
{
    $me = Member::factory()->create();
    CommunityMessage::factory()->for($me)->create(['subject' => '我的主旨', 'body' => '我的內文秘密', 'admin_note' => '內部備註', 'status' => 'forwarded']);
    CommunityMessage::factory()->create(['subject' => '別人的主旨']);

    $this->asMember($me)->get(route('member.community-messages'))
        ->assertOk()
        ->assertSee('我的主旨')
        ->assertSee(__('community-messages.status.forwarded'))
        ->assertDontSee('別人的主旨')
        ->assertDontSee('我的內文秘密')
        ->assertDontSee('內部備註');
}

public function test_the_list_requires_login_and_has_english_page(): void
{
    $this->get(route('member.community-messages'))->assertRedirect(route('member.login'));
    $this->asMember(Member::factory()->create())->get(route('member.community-messages.en'))
        ->assertOk()
        ->assertSee(trans('community-messages.list.heading', [], 'en'), false);
}

public function test_the_member_center_links_to_the_list(): void
{
    $this->asMember(Member::factory()->create())->get(route('member.center'))
        ->assertSee(route('member.community-messages'), false);
}
```

測試需要的 import：`App\Models\{CommunityMessage, Member}`、`App\Notifications\CommunityMessageReceivedNotification`、`Illuminate\Support\Facades\{Auth, Notification, RateLimiter}`、`RuntimeException`。`test_a_mail_failure...` 若 `Notification::shouldReceive` 與 fake 不相容，改為在測試內以 `Notification::fake()` 之外的方式模擬，例如暫時把 `mail.default` 設為一個不存在的 mailer，目的是驗證不影響送出。

- [ ] **Step 2: 驗證失敗**

Run: `php artisan test --compact tests/Feature/CommunityMessages/CommunityMessageFlowTest.php`
Expected: FAIL，路由不存在。

- [ ] **Step 3: 實作**

`AppServiceProvider::boot()`：

```php
RateLimiter::for('member-community-message', fn (Request $request) => Limit::perMinute(5)
    ->by('member-community-message|'.($request->user('member')?->getKey() ?? $request->ip())));
```

`routes/web.php` 在已驗證群組內加入，並 `use App\Http\Controllers\Member\CommunityMessageController;`：

```php
Route::get('/member/communities/{slug}/message', [CommunityMessageController::class, 'create'])->name('member.community-message.create'.$s);
Route::post('/member/communities/{slug}/message', [CommunityMessageController::class, 'store'])->middleware('throttle:member-community-message')->name('member.community-message.store'.$s);
Route::get('/member/community-messages', [CommunityMessageController::class, 'index'])->name('member.community-messages'.$s);
```

控制器：

```php
<?php

namespace App\Http\Controllers\Member;

use App\Models\CommunityDirectory;
use App\Models\CommunityMessage;
use App\Notifications\CommunityMessageReceivedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CommunityMessageController extends MemberController
{
    public function create(string $slug): View
    {
        $community = CommunityDirectory::find($slug);

        abort_if($community === null, 404);

        return view('member.community-message-create', [
            'community' => $community,
            'communityName' => CommunityDirectory::name($community),
            'slug' => $slug,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $community = CommunityDirectory::find($slug);

        abort_if($community === null, 404);

        $me = $this->guard()->user();

        foreach (['subject', 'body'] as $field) {
            $value = $request->input($field);

            $request->merge([$field => is_string($value) ? trim($value) : $value]);
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:'.CommunityMessage::SUBJECT_LENGTH],
            'body' => ['required', 'string', 'max:'.CommunityMessage::BODY_LENGTH],
            'agree_conduct' => ['accepted'],
        ]);

        $communityName = CommunityDirectory::name($community, 'zh_TW');

        Cache::lock('community-message:'.$me->getKey(), 10)->block(5, function () use ($me, $data, $slug, $communityName): void {
            if (CommunityMessage::sentTodayBy($me) >= CommunityMessage::DAILY_LIMIT) {
                throw ValidationException::withMessages(['body' => __('community-messages.errors.daily_limit')]);
            }

            CommunityMessage::query()->create([
                'member_id' => $me->getKey(),
                'community_slug' => $slug,
                'community_name' => $communityName,
                'subject' => $data['subject'],
                'body' => $data['body'],
            ]);
        });

        $this->sendQuietly(function () use ($me, $communityName): void {
            $me->notify(
                (new CommunityMessageReceivedNotification($communityName, route('member.community-messages')))->locale('zh_TW'),
            );
        });

        return redirect($this->localizedRoute('member.community-messages'))
            ->with('status', __('community-messages.flash.sent'));
    }

    public function index(): View
    {
        $me = $this->guard()->user();

        return view('member.community-messages', [
            'messages' => CommunityMessage::query()
                ->where('member_id', $me->getKey())
                ->latest()
                ->get(['id', 'community_name', 'subject', 'status', 'created_at']),
        ]);
    }
}
```

通知類別 `CommunityMessageReceivedNotification(string $communityName, string $url)`，`via` 回傳 `['mail']`，`toMail` 使用 `community-messages.mail.*`：主旨「你的訊息已送出」、`intro` 含社群名稱的「你的訊息已送給 :community，主辦方會轉交給社群。」、按鈕「查看處理狀態」、`outro`「為了保護隱私，信中不會顯示訊息內容。」。

視圖沿用 `member.layout`：

- `community-message-create.blade.php`：標題含社群名稱，主旨 `input`（`maxlength=100`）、內文 `textarea`（`maxlength=1000`）、`agree_conduct` 核取方塊與 `route('conduct'.$s)` 連結、送出按鈕，說明主辦方會轉交且不保證回覆。
- `community-messages.blade.php`：每筆顯示社群名稱、主旨、送出時間與狀態文案，不輸出內文與備註，空狀態文案。

`lang/zh_TW/community-messages.php` 的鍵：`create.title`、`create.heading`、`create.intro`、`create.subject`、`create.body`、`create.agree`、`create.conduct_link`、`create.submit`；`list.title`、`list.heading`、`list.empty`、`list.to_communities`；`status.pending`（待處理）、`status.forwarded`（已轉交）、`status.closed`（已結案）；`errors.daily_limit`；`flash.sent`；`mail.subject`、`mail.intro`、`mail.action`、`mail.outro`；`contact`（卡片連結文案「聯絡這個社群」）。`lang/en` 提供對應英文，`list.heading` 為 `Messages to communities`。

`center.blade.php` 在漂流瓶連結之後加入連到 `member.community-messages` 的連結，文案 `__('community-messages.list.heading')`。

- [ ] **Step 4: 驗證通過並 Commit**

Run: `php artisan test --compact tests/Feature/CommunityMessages`
Expected: PASS。

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Let members send messages to partner communities"
```

---

### Task 3: 合作社群頁的聯絡連結

**Files:**
- Modify: `resources/views/components/community-card.blade.php`、`resources/views/communities.blade.php`
- Test: `tests/Feature/CommunityMessages/CommunityCardTest.php`

**Interfaces:**
- Consumes: 路由 `member.community-message.create` 與 `.en`，`community-messages.contact`
- Produces: `x-community-card` 新增選用屬性 `:show-contact`

- [ ] **Step 1: 寫失敗的測試**

```php
public function test_every_card_on_the_communities_page_links_to_its_message_form(): void
{
    $response = $this->get('/communities')->assertOk();

    foreach (config('communities') as $community) {
        $response->assertSee(route('member.community-message.create', $community['slug']), false);
    }
}

public function test_the_english_page_links_to_the_english_form(): void
{
    $this->get('/en/communities')
        ->assertSee(route('member.community-message.create.en', 'gdg-taoyuan'), false);
}

public function test_the_landing_cards_have_no_contact_link(): void
{
    $this->get('/')->assertOk()->assertDontSee('/member/communities/', false);
}

public function test_a_card_keeps_a_single_outbound_link_to_the_community(): void
{
    $html = $this->get('/communities')->getContent();

    $this->assertSame(
        count(config('communities')),
        substr_count($html, 'rel="noopener"') - substr_count($html, 'rel="noopener noreferrer"'),
    );
}
```

最後一個測試只在頁面其他連結的 `rel` 寫法不干擾時使用，若頁尾或標頭已有相同 `rel`，改為斷言每個社群 `url` 恰好出現一次。

- [ ] **Step 2: 驗證失敗**

Run: `php artisan test --compact tests/Feature/CommunityMessages/CommunityCardTest.php`
Expected: FAIL，沒有聯絡連結。

- [ ] **Step 3: 實作**

`community-card.blade.php`：

```blade
@props(['community', 'showDescription' => false, 'showContact' => false])
@php
    $name = $community['name'][app()->getLocale()];
    $suffix = app()->getLocale() === 'en' ? '.en' : '';
@endphp
<div class="group flex h-full flex-col gap-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6 text-center transition hover:border-moss">
    <a href="{{ $community['url'] }}" target="_blank" rel="noopener" class="flex flex-1 flex-col items-center gap-4">
        <x-circle-logo :src="Vite::asset('resources/images/community-logo/'.$community['logo'])" :alt="$name" :fit="$community['logo_fit']" :padding="$community['logo_padding'] ?? 15" />
        <span class="text-lg font-semibold">{{ $name }}</span>
        @if ($showDescription)
            <span class="text-sm leading-relaxed text-ink/70">{{ $community['description'][app()->getLocale()] }}</span>
        @endif
        <span class="text-sm font-medium text-moss group-hover:underline">{{ __('communities.visit') }}</span>
    </a>
    @if ($showContact)
        <a href="{{ route('member.community-message.create'.$suffix, ['slug' => $community['slug']]) }}"
           class="text-sm font-semibold text-moss underline underline-offset-4">
            {{ __('community-messages.contact') }}
        </a>
    @endif
</div>
```

`communities.blade.php` 的卡片改為 `<x-community-card :community="$community" show-description show-contact />`。

- [ ] **Step 4: 驗證通過並確認既有頁面測試**

Run: `php artisan test --compact tests/Feature/CommunityMessages tests/Feature/ExampleTest.php`
Expected: PASS，既有的 `test_communities_pages_list_partner_logos_in_both_languages` 仍通過。

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Link partner community cards to the message form"
```

---

### Task 4: 後台

**Files:**
- Create: `app/Filament/Resources/CommunityMessages/*`（以 `php artisan make:filament-resource CommunityMessage --view --no-interaction` 產生，再刪除 `Create`、`Edit` 頁面與 `Form`）
- Test: `tests/Feature/CommunityMessages/CommunityMessageAdminTest.php`

**Interfaces:**
- Consumes: `CommunityMessage::markAs`、`scopePending`、`STATUS_*`
- Produces: `CommunityMessageResource`，頁面 `ListCommunityMessages`、`ViewCommunityMessage`

- [ ] **Step 1: 寫失敗的測試**

`setUp` 以 `User::factory()->create(['email' => 'flamerecca711@gmail.com'])` 登入。

```php
public function test_list_shows_messages_and_filters_by_status_and_community(): void
{
    $pending = CommunityMessage::factory()->create();
    $closed = CommunityMessage::factory()->create(['status' => 'closed', 'community_slug' => 'iplayground', 'community_name' => 'iPlayground']);

    Livewire::test(ListCommunityMessages::class)
        ->assertCanSeeTableRecords([$pending, $closed])
        ->filterTable('status', 'closed')
        ->assertCanSeeTableRecords([$closed])
        ->assertCanNotSeeTableRecords([$pending])
        ->resetTableFilters()
        ->filterTable('community_slug', 'gdg-taoyuan')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$closed]);
}

public function test_list_can_search_by_subject_body_and_member(): void
{
    $member = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com']);
    $match = CommunityMessage::factory()->for($member)->create(['subject' => '講座合作', 'body' => '咖啡']);
    $other = CommunityMessage::factory()->create(['subject' => '無關', 'body' => '其他']);

    Livewire::test(ListCommunityMessages::class)
        ->searchTable('講座')->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other])
        ->searchTable('alice@example.com')->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
}

public function test_the_view_page_shows_member_details_and_content(): void
{
    $member = Member::factory()->create(['name' => 'Alice Wang', 'email' => 'alice@example.com', 'organization' => '某公司']);
    $message = CommunityMessage::factory()->for($member)->create(['subject' => '主旨', 'body' => '內文']);

    Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
        ->assertSee('Alice Wang')->assertSee('alice@example.com')->assertSee('某公司')
        ->assertSee('主旨')->assertSee('內文')->assertSee('GDG 桃園');
}

public function test_status_actions_record_the_handler(): void
{
    $message = CommunityMessage::factory()->create();

    Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
        ->callAction('markForwarded')->assertNotified();
    $this->assertSame('forwarded', $message->fresh()->status);
    $this->assertSame(auth()->id(), $message->fresh()->handled_by);

    Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
        ->callAction('markClosed')->assertNotified();
    $this->assertSame('closed', $message->fresh()->status);

    Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
        ->callAction('reopen')->assertNotified();
    $this->assertSame('pending', $message->fresh()->status);
    $this->assertNull($message->fresh()->handled_by);
}

public function test_the_note_can_be_edited_but_not_the_content(): void
{
    $message = CommunityMessage::factory()->create(['subject' => '原主旨', 'body' => '原內文']);

    Livewire::test(ViewCommunityMessage::class, ['record' => $message->getKey()])
        ->callAction('editNote', ['admin_note' => '已用 Email 轉給社群'])
        ->assertNotified();

    $fresh = $message->fresh();
    $this->assertSame('已用 Email 轉給社群', $fresh->admin_note);
    $this->assertSame('原主旨', $fresh->subject);
    $this->assertSame('原內文', $fresh->body);
}

public function test_the_resource_has_no_create_edit_or_delete(): void
{
    $message = CommunityMessage::factory()->create();

    $this->assertFalse(CommunityMessageResource::canCreate());
    $this->assertFalse(CommunityMessageResource::canEdit($message));
    $this->assertFalse(CommunityMessageResource::canDelete($message));
    $this->assertFalse(CommunityMessageResource::canDeleteAny());
}

public function test_the_navigation_badge_counts_pending_messages(): void
{
    CommunityMessage::factory()->count(2)->create();
    CommunityMessage::factory()->create(['status' => 'closed']);

    $this->assertSame('2', CommunityMessageResource::getNavigationBadge());
}

public function test_the_badge_is_hidden_when_nothing_is_pending(): void
{
    CommunityMessage::factory()->create(['status' => 'closed']);

    $this->assertNull(CommunityMessageResource::getNavigationBadge());
}
```

- [ ] **Step 2: 驗證失敗**

Run: `php artisan test --compact tests/Feature/CommunityMessages/CommunityMessageAdminTest.php`
Expected: FAIL，資源不存在。

- [ ] **Step 3: 實作**

以 `MessageResource` 與 `ViewDriftBottle` 為範本：

- `CommunityMessageResource`：`navigationGroup = '會員'`、`navigationSort = 50`、`navigationIcon = Heroicon::OutlinedBuildingOffice2`、標籤「社群訊息」，`getPages()` 只有 `index` 與 `view`，`canCreate/canEdit/canDelete/canDeleteAny` 皆 false。
  `getNavigationBadge(): ?string` 回傳待處理數量字串，為 0 時回傳 `null`，`getNavigationBadgeColor()` 回傳 `'warning'`。
- 列表欄位：寄件會員（可搜尋姓名與 Email，寫法同 `MessagesTable`）、社群名稱、主旨（可搜尋）、狀態 badge、送出時間。內文也要能被搜尋，用 `TextColumn::make('body')->searchable()->toggleable(isToggledHiddenByDefault: true)`。
- 篩選：`SelectFilter::make('status')`，選項為三種狀態的中文名稱；`SelectFilter::make('community_slug')`，選項由 `CommunityDirectory::all()` 以 `slug => 繁體中文名稱` 組成；送出日期區間。
- 檢視頁 infolist：寄件會員姓名、Email、所屬組織、社群、主旨、內文、狀態、處理人、處理時間、備註。
- `ViewCommunityMessage::getHeaderActions()`：`markForwarded`、`markClosed`、`reopen`，依狀態決定是否顯示，皆呼叫 `$record->markAs(..., auth()->id())` 並送出通知；`editNote` 以 `Textarea` 表單更新 `admin_note`，預設值為現有備註。

- [ ] **Step 4: 驗證通過並 Commit**

Run: `php artisan test --compact tests/Feature/CommunityMessages`
Expected: PASS。

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "Add the community message inbox to the admin panel"
```

---

### Task 5: 整體驗證

- [ ] **Step 1: 路由與全套測試**

Run: `php artisan route:list --name=community-message`
Expected: `member.community-message.create`、`.store`、`member.community-messages` 與各自的 `.en`。

Run: `vendor/bin/pint --format agent && php artisan test --compact`
Expected: 全部 PASS，記錄測試數量。若 Pint 改動 `bootstrap/providers.php`，還原它。

- [ ] **Step 2: Commit 剩餘變更**

```bash
git status --short
git add -A && git commit -m "Finish community messages verification"
```

沒有變更則略過。

## Self-Review

- **Spec 覆蓋：** `slug`、資料表與模型在 Task 1，送出、限額、確認信與會員端在 Task 2，合作社群頁連結在 Task 3，後台與導覽標籤在 Task 4，全套驗證在 Task 5。
- **Placeholder 檢查：** 視圖與文案以結構與鍵清單描述，實作時沿用既有 `member.layout` 風格，不屬未決事項。
- **型別一致：** `CommunityDirectory::find/name`、`CommunityMessage::sentTodayBy/markAs/scopePending` 與常數在各任務使用一致。
- **規格差異：** 已在決策紀錄說明社群卡片外層結構調整。
