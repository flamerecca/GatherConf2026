# 會員管理系統設計規格

日期：2026-10-13
狀態：待審閱

## 1. 背景與目標

GatherConf 官網目前是公開的靜態內容，沒有會員功能。Filament 後台已有 `AdminPanelProvider`，但 `app/Filament` 底下還沒有任何 Resource，現有 `users` 資料表只用於後台管理者登入。

本規格新增「官網註冊會員」，並在 Filament 後台提供管理功能。

成功條件：

1. 訪客可以在官網以 Email 與密碼註冊、驗證 Email、登入與登出，並可重設密碼。
2. 後台管理者可以在 Filament 後台查詢、檢視、編輯、停用與重新啟用會員。
3. 會員帳號與後台管理者帳號完全分離，會員不可能進入 `/admin`。

## 2. 已確認的決策

| 項目 | 決策 |
| :---- | :---- |
| 會員定義 | 在官網註冊的會員，不是後台管理者，也不是活動報名名單。 |
| 範圍 | 後台管理加上前台註冊登入，分兩個階段實作。 |
| 帳號模型 | 獨立 `Member` 模型與獨立 guard，不與 `users` 共用資料表。 |
| 會員欄位 | 姓名、Email、密碼、Email 驗證、啟用狀態、所屬社群或公司。 |
| 套件 | 不新增任何依賴，使用 Laravel 內建的認證機制手寫。 |
| 後台限制 | 後台不新增會員，也不修改會員密碼。 |
| `quiz_attempts.user_id` | 改為綁定 `members`，因專案內目前沒有程式使用此欄位。 |

## 3. 不在範圍內

- 社群登入，如 Google、Facebook。
- 會員等級、點數與活動報名。
- 管理者代為建立會員或代為修改密碼。
- 前台個人資料編輯頁，之後另開需求。
- 正式環境的郵件服務設定，僅說明前置條件，見第 8 節。

## 4. 資料層

### 4.1 `members` 資料表

| 欄位 | 型別 | 說明 |
| :---- | :---- | :---- |
| `id` | bigint | 主鍵 |
| `name` | string | 姓名，必填 |
| `email` | string | 唯一，必填 |
| `email_verified_at` | timestamp，可為空 | Email 驗證時間 |
| `password` | string | 雜湊後的密碼 |
| `organization` | string，可為空 | 所屬社群或公司 |
| `is_active` | boolean，預設 true | 啟用狀態，停用後無法登入 |
| `remember_token` | string，可為空 | 記住我 |
| `created_at`、`updated_at` | timestamp | 時間戳記 |

`email` 與 `is_active` 加上索引，後台列表會依這兩個欄位查詢與篩選。

### 4.2 `member_password_reset_tokens` 資料表

結構與 `password_reset_tokens` 相同，獨立一張表，避免會員與後台管理者因 Email 相同而互相覆蓋重設標記。

### 4.3 `Member` 模型

- 位於 `app/Models/Member.php`，繼承 `Authenticatable`，實作 `MustVerifyEmail`。
- 依 `.ai/rules/models.md`，使用 `#[Fillable]` 與 `#[Hidden]` 屬性，不使用 `$fillable` 與 `$hidden` 屬性寫法。
- `casts` 包含 `email_verified_at` 為 datetime、`password` 為 hashed、`is_active` 為 boolean。
- 提供 `MemberFactory`，含 `unverified()` 與 `inactive()` 狀態，並提供 `MemberSeeder`。

### 4.4 `quiz_attempts.user_id` 遷移

新增 migration，把 `quiz_attempts.user_id` 的外鍵由 `users` 改為 `members`，保留 nullable 與 `nullOnDelete`。`QuizAttempt` 補上 `member(): BelongsTo` 關聯。

## 5. 認證組態

`config/auth.php` 新增：

- guard `member`，driver 為 `session`，provider 為 `members`。
- provider `members`，driver 為 `eloquent`，model 為 `Member`。
- password broker `members`，使用 `member_password_reset_tokens` 資料表。

原本的 `web` guard、`users` provider 與 Filament 後台的認證完全不變。

## 6. 後台，階段一

### 6.1 `MemberResource`

位於 `app/Filament/Resources/MemberResource`，以 `php artisan make:filament-resource` 產生，並遵守 Filament v4 的命名空間規則。

**列表頁**

- 欄位：姓名、Email、所屬社群或公司、啟用狀態、Email 驗證狀態、註冊時間。
- 搜尋：姓名、Email、所屬社群或公司。
- 篩選：啟用狀態、是否已驗證 Email。
- 預設依註冊時間倒序。

**檢視與編輯頁**

- 可編輯：姓名、所屬社群或公司、啟用狀態。
- 唯讀顯示：Email、Email 驗證時間、註冊時間。
- 不顯示也不可修改密碼。
- 不提供建立頁，也不提供刪除。會員以停用處理，保留紀錄。

**動作**

- 停用與重新啟用，附確認視窗。
- 手動標記為已驗證，只對未驗證會員顯示。
- 動作完成後以通知回報結果。

### 6.2 權限

沿用目前後台的存取方式，所有可登入後台的管理者都可管理會員。更細緻的角色權限不在本次範圍。

## 7. 前台，階段二

### 7.1 路由

沿用現有中英文雙語路由結構，英文頁面加 `/en` 前綴，並使用具名路由。

| 功能 | 中文路徑 | 說明 |
| :---- | :---- | :---- |
| 註冊 | `/register` | 訪客限定 |
| 登入 | `/login` | 訪客限定 |
| 登出 | `/logout`，POST | 會員限定 |
| Email 驗證提示 | `/email/verify` | 已登入但未驗證 |
| Email 驗證連結 | `/email/verify/{id}/{hash}` | 簽章連結 |
| 忘記密碼 | `/forgot-password` | 訪客限定 |
| 重設密碼 | `/reset-password/{token}` | 訪客限定 |

註冊後自動登入並導向 Email 驗證提示頁。

### 7.2 行為規則

- 已停用的會員登入時顯示明確訊息，不發出登入 Session。
- 已登入的停用會員，下一個請求即被登出。以 middleware 檢查 `is_active` 實作。
- 登入、註冊與忘記密碼加上 rate limiting，登入每 Email 與 IP 組合每分鐘 5 次，註冊與忘記密碼每 IP 每分鐘 5 次。
- 錯誤訊息不透露某個 Email 是否已註冊，登入失敗一律顯示相同文字。註冊時 Email 重複則依 Laravel 預設的確認規則提示。

### 7.3 畫面

使用現有 layout 與設計系統，頁面為單欄表單，符合 `docs/design-system.md` 的色彩、字級與圓角規範。繁體中文文案放入 `lang/zh_TW`，英文放入 `lang/en`，台灣用語依全域術語規則。

## 8. 前置條件與風險

| 項目 | 說明 |
| :---- | :---- |
| 郵件寄送 | Email 驗證與重設密碼需要可用的 mailer。開發環境使用 `MAIL_MAILER=log`，正式環境需另行設定寄信服務，本次不處理。 |
| Session 表 | `sessions.user_id` 欄位會存放會員 id，與管理者 id 數值可能相同。本專案不使用該欄位做查詢，故不處理，僅記錄。 |
| 個資 | 會員資料含姓名與 Email，後台僅管理者可見，不寫入日誌。 |
| 既有資料 | `quiz_attempts.user_id` 目前沒有程式寫入，遷移不需要搬資料。實作前需再確認資料表內實際沒有資料。 |

## 9. 測試

全部使用 PHPUnit feature 測試，並以 factory 建立資料。

**階段一**

- 後台列表可看到會員，搜尋與篩選正確。
- 編輯姓名、所屬社群或公司與啟用狀態可儲存。
- 停用、重新啟用與手動驗證動作正確更新資料。
- 後台沒有建立頁、沒有刪除，密碼欄位不出現。
- `quiz_attempts` 遷移後外鍵指向 `members`。

**階段二**

- 註冊成功、欄位驗證失敗、Email 重複。
- 驗證信寄出、驗證連結成功與簽章無效。
- 登入成功、密碼錯誤、停用會員被拒絕、已登入會員被停用後下一個請求即被登出。
- 會員無法進入 `/admin`，管理者登入不影響會員 guard。
- 忘記與重設密碼流程。
- rate limiting 超過次數後回應 429。
- 中英文頁面各自顯示正確語言。

## 10. 實作階段

1. 階段一：資料層、認證組態、`MemberResource` 與測試。
2. 階段二：前台註冊登入流程、雙語文案、測試與設計文件更新。

兩個階段各自產出實作計畫與提交，階段一完成並驗證後再開始階段二。
