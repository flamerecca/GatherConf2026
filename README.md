# 共聚年會 GatherConf 2026

共聚年會是一場讓不同技術社群坐在同一個空間裡的年會。技術社群越分越細，彼此之間的牆也越來越高，共聚年會想把牆打開，讓大家真正聊上幾句話。

- 日期：2026-11-21
- 地點：台大綜合教學館 綜201-203

這個儲存庫是共聚年會的官網與會員系統，部署在 Laravel Cloud。

## 功能

- 官網首頁：活動簡介、議程、合作社群、贊助方案與贊助商，提供繁體中文與英文，英文網址前綴為 `/en`。
- 其他公開頁面：議程 `/agenda`、行為準則 `/code-of-conduct`、合作社群 `/communities`、贊助合作 `/call-for-sponsor`。
- 會員系統：註冊、Email 驗證、密碼或登入連結登入、暱稱編輯、好友邀請與訊息、封鎖。
- 胡蘿蔔任務：站上行為與場域任務可以賺取胡蘿蔔，點數記錄在不可修改的帳本，可由管理員補發或撤銷，餘額允許為負。
- 漂流瓶與社群留言。
- BBTI 創客兔性格測驗：導引頁 `/bbti`、作答頁 `/quiz`、結果頁 `/results/{uuid}`。
- 管理後台 `/admin`：以 Filament 管理會員、任務、胡蘿蔔交易、漂流瓶、訊息與好友關係。

## 技術堆疊

- PHP 8.3 以上，Laravel 13
- Filament 4 管理後台
- Blade 搭配 Tailwind CSS 4，以 Vite 建置
- 資料庫預設為 SQLite
- 測試使用 PHPUnit，格式使用 Laravel Pint
- 郵件使用 Mailgun

## 本機開發

```bash
composer setup
composer dev
```

`composer setup` 會安裝依賴、建立 `.env`、產生金鑰、執行遷移並建置前端資源檔。`composer dev` 會一併啟動開發伺服器、佇列、日誌與前端的 Vite。

BBTI 測驗的題目與 16 種類型需要資料填充：

```bash
php artisan db:seed --class=PersonalityTypeSeeder
php artisan db:seed --class=QuizQuestionSeeder
```

這兩個 Seeder 重複執行會重複建立資料，每個環境只要跑一次。

### 環境變數

| 變數 | 說明 |
| :--- | :--- |
| `ADMIN_EMAILS` | 可進入 `/admin` 的帳號 Email，以逗號分隔，帳號本身需先建立在 `users` 資料表。 |
| `MAILGUN_DOMAIN`、`MAILGUN_SECRET` | 寄送驗證信與登入連結用的 Mailgun 憑證。 |
| `MAIL_FROM_ADDRESS` | 寄件者信箱。 |

### 排程

正式環境需要執行 Laravel 排程器，會用到兩個排程：

- `member-login-links:prune`：每天清除過期的登入連結。
- `drift-bottles:release`：每小時處理漂流瓶的釋出。

## 測試與格式

```bash
composer test
vendor/bin/pint --dirty
```

## 內容維護

不需要改程式碼就能更新的內容放在組態檔：

- 議程：`config/agenda.php`，講者照片預計放在 `resources/images/speakers/`，目前尚未建立。
- 贊助商：`config/sponsors.php`，Logo 放在 `resources/images/sponsor-logo/`。
- 合作社群：`config/communities.php`。
- 站上胡蘿蔔獎勵：`config/carrots.php`。
- 多語系文字：`lang/zh_TW` 與 `lang/en`。

## 文件

規格與設計文件放在 `docs/`：

- `design-system.md`：視覺設計系統與 WCAG 2.2 AA 無障礙規範。
- `filament-admin-guide.md`：管理後台操作指南。
- `gatherconf-quest-rewards-spec.md`、`gatherconf-quest-data-model.md`：胡蘿蔔任務與獎勵規格、資料模型。
- `mbti-quiz-planning.md`、`screen-design-spec.md`、`bbti-16-types-spec.md`：BBTI 測驗的規劃、畫面與類型內容。
- `docs/superpowers/`：各階段的設計與實作計畫。

## 授權

本專案以 [MIT 授權](https://opensource.org/licenses/MIT) 釋出。
