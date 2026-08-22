# GatherConf2026 旅人與資料模型規格書

本文件是 `docs/gatherconf-quest-rewards-spec.md`（聚落任務與獎勵規格書）的資料表設計，獨立拆出方便工程實作時查閱，任務內容、通行證頁面、任務進度頁面等產品規劃仍以主文件為準。

「旅人」就是已用 Google 登入的 `App\Models\User`（見 `docs/mbti-quiz-planning.md` §6 的登入規劃），不是獨立的角色或帳號系統——本文件只是在既有 `users` 表上擴充通行證欄位，並新增任務／獎勵相關的表。

---

## 1. `users`（既有表，新增欄位）

| 欄位 | 型別 | 說明 |
|---|---|---|
| passport_code | string(10), unique | 十碼數字通行證代號（見 `docs/gatherconf-quest-rewards-spec.md` §4.1），建立帳號時隨機產生，**不可為流水號或遞增值** |

沿用專案既有 `#[ObservedBy]` 慣例（`QuizAttemptObserver` 的模式），新增一個 `UserObserver` 在 `created` 事件隨機產生不重複的十碼代號並寫入。

---

## 2. `quest_tasks`（任務項目，Filament 後台可編輯）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | |
| code | enum(`Q1`..`Q8`) | 對應 `docs/gatherconf-quest-rewards-spec.md` §2 的任務代號 |
| name | string | 任務名稱，如「拜訪據點 - A 攤位」 |
| description | text | 任務說明 |
| points | unsigned int | 完成可得點數 |
| scan_token | string, unique, nullable | Q3 攤位 QR Code 內編碼的隨機字串，**不可用 `id` 直接當 QR 內容**（`id` 是遞增數字，會被輕易枚舉猜出其他攤位的連結）。只有 Q3 類型的任務需要這個欄位，其餘任務為 null |
| requires_manual_review | boolean, default false | 標記 Q6 這類需要人工審核才能登記的任務 |
| is_active | boolean, default true | 下架任務不列入計算 |
| order | unsigned int | 顯示順序 |

**Q3／Q4 用多筆資料表示**：每個攤位、每場議程各自是一筆獨立的 `quest_tasks` 資料列（`code` 都是 `Q3` 或 `Q4`，但 `name` 不同），例如「拜訪據點 - A 攤位」「拜訪據點 - B 攤位」分別是兩筆。這樣一個人才能在多個攤位／場次分別得分，也才能用資料庫層級的唯一性約束防止同一攤位被同一人重複登記（見第 3 節）。

**Q3 的 QR Code 內容**：印給每個攤位的實體 QR Code，掃描後導向的網址帶著該筆任務的 `scan_token`（例如 `/quests/scan/{scan_token}`），而不是帶 `quest_tasks.id`，避免有心人直接把網址裡的數字換掉、跳過掃描去猜其他攤位的連結。

---

## 3. `quest_completions`（任務完成紀錄）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | |
| user_id | FK users | 完成任務的旅人 |
| quest_task_id | FK quest_tasks | |
| points_awarded | unsigned int | 完成當下的點數快照，避免任務點數之後調整時影響歷史紀錄 |
| registered_by | nullable FK users | 登記者：Q4／Q6 是工作人員帳號、Q5 是對方旅人；**Q3 是旅人自己掃攤位 QR 完成，沒有「登記者」這個角色，此欄位為 null** |
| completed_at | timestamp | |

**唯一性約束**：`(user_id, quest_task_id)` 唯一——同一人對同一筆任務（同一攤位／場次）只能登記一次，天然防止重複登記灌水，但仍需搭配存取權限限制才算完整（唯一性約束防不了工作人員裝置被盜用來重複登記別人，見 `docs/gatherconf-quest-rewards-spec.md` 待確認事項）。

---

## 4. `reward_tiers`（獎勵層級，Filament 後台可編輯）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | |
| threshold_points | unsigned int | 兌換門檻點數 |
| name | string | 層級名稱，如「拓荒者」 |
| prize_description | text | 獎品內容說明（目前是佔位文字，實際獎品清單見 `docs/gatherconf-quest-rewards-spec.md` 待確認事項） |
| is_active | boolean, default true | |

---

## 5. `reward_redemptions`（兌換紀錄）

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | bigint | |
| user_id | FK users | |
| reward_tier_id | FK reward_tiers | |
| redeemed_at | timestamp | |
| redeemed_by | nullable FK users | 兌換櫃檯工作人員帳號 |

**唯一性約束**：`(user_id, reward_tier_id)` 唯一，同一人同一層級只能兌換一次。

---

以上新表皆沿用專案既有的 `SoftDeletes` 慣例（`users` 表本身目前沒有 SoftDeletes，維持原狀不動）。

**累積點數不另外快取欄位**：旅人目前累積點數用 `quest_completions.points_awarded` 加總查詢即可（`SUM`），不在 `users` 加一個 `total_points` 快取欄位——活動規模不大，即時查詢就夠用，避免為了效能疑慮而過早引入快取欄位跟同步邏輯的複雜度。
