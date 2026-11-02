# 胡蘿蔔系統

本文件說明會員的胡蘿蔔，包含取得方式、記帳規則、資料表與後台管理。會員任務頁的版面與按鈕見 `docs/member-tasks-page.md`，後台操作見 `docs/filament-admin-guide.md`。

胡蘿蔔是會員完成任務可以累積的點數。系統只負責記錄與顯示餘額，目前沒有獎勵層級、獎品清單或兌換紀錄，實體獎品的發放不在系統內處理。

---

## 1. 取得方式

| 方式 | 說明 | 重複規則 |
|---|---|---|
| 站上任務 | 在網站上完成一次性的行為，由系統自動判定 | 每位會員每項只能獲得一次 |
| 現場任務 | 後台建立，會員掃描 QR Code 或輸入短碼完成 | 每位會員每項任務只能獲得一次 |
| 授權碼 | 後台建立，會員在任務頁輸入 | 每位會員每組只能兌換一次，可設定總次數上限 |
| 手動調整 | 管理員在會員頁面加減胡蘿蔔 | 每次調整都是獨立的一筆 |

### 1.1 站上任務

獎勵數量定義在 `config/carrots.php` 的 `site`，數量為 0 代表關閉該項。

| 代碼 | 任務 | 獎勵 | 觸發時機 |
|---|---|---|---|
| `verify_email` | 驗證 Email | 5 | 會員的 Email 驗證時間第一次被寫入 |
| `set_password` | 設定密碼 | 3 | 會員第一次為帳號設定密碼 |
| `complete_quiz` | 完成人格測驗 | 5 | 已登入且 Email 已驗證的會員第一次完成測驗，訪客完成測驗後註冊綁定結果時也會獲得 |
| `first_friend` | 交到第一位好友 | 5 | 好友邀請被接受，雙方各獲得一次 |
| `first_message` | 傳出第一則訊息 | 2 | 第一次成功傳訊息給好友 |

### 1.2 現場任務

後台「胡蘿蔔任務」建立，欄位有名稱、說明、獎勵數量、啟用開關與開始、結束時間。

- 每個任務有兩種完成方式：
  - 掃描專屬 QR Code，網址為 `/member/tasks/{code}`，`code` 是 32 碼的隨機字串，不使用遞增 id，避免被人枚舉其他任務的連結。
  - 在任務頁輸入 8 碼的短碼 `short_code`。
- 任務必須啟用，且現在時間在開始與結束時間之間，才能完成。
- 會員需要登入並已驗證 Email。
- 同一位會員對同一個任務只能獲得一次。

### 1.3 授權碼

後台「授權碼」建立，細節見 `docs/member-tasks-page.md` 第 5 節，欄位與操作見 `docs/filament-admin-guide.md` 第 8 節。

### 1.4 手動調整

在後台會員頁面按「調整胡蘿蔔」，數量可以是正數或負數，範圍為 -100000 到 100000，並需要填寫原因。餘額允許為負數。

---

## 2. 記帳規則

每一筆胡蘿蔔的增減都記在 `carrot_transactions`，會員餘額 `members.carrots` 是這些紀錄的加總。

- **紀錄不可修改也不可刪除**。要取消某次獲得，新增一筆相反數量的紀錄，原本那筆保持不動。
- **防止重複**：每筆紀錄有唯一的 `dedupe_key`，已存在就不再新增。
  - 站上任務：`{代碼}:{會員 id}`
  - 現場任務：`task:{任務 id}:{會員 id}`
  - 授權碼：`code:{授權碼 id}:{會員 id}`
  - 手動調整：`manual:{隨機 id}`，每次都不同
  - 取消：`revoke:{原本的 dedupe_key}`，同一筆只能取消一次
- **取消後不能再獲得**：已取消的任務或站上任務，因為原本的 `dedupe_key` 還在，會員不能再次獲得。
- **餘額範圍**：`members.carrots` 是有號整數，範圍為 -2147483648 到 2147483647，超出時拒絕記帳。
- **並發安全**：記帳時鎖定該會員的資料列，避免同時完成同一個任務造成重複，授權碼的次數上限也在同一個交易內檢查並鎖定。
- **站上任務不影響主要流程**：站上任務的記帳失敗時，只回報錯誤，不會讓註冊、測驗或傳訊息等主要行為失敗。

記帳的實作在 `App\Models\CarrotLedger`。

---

## 3. 資料表

### 3.1 `members.carrots`

有號整數，預設 0，會員目前的胡蘿蔔餘額。

### 3.2 `carrot_tasks`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | uuid | |
| name | string | 任務名稱 |
| description | text, nullable | 說明 |
| reward | unsigned int | 獎勵數量 |
| code | string(32), unique | QR Code 網址用的隨機字串 |
| short_code | string(32), unique, nullable | 可輸入的 8 碼短碼 |
| is_active | boolean, default true | |
| starts_at、ends_at | timestamp, nullable | 開放期間，空值代表不限 |

### 3.3 `authorization_codes`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | uuid | |
| name | string | 給管理員辨識的名稱 |
| code | string(32), unique | 授權碼，大寫英數字 |
| reward | unsigned int | 獎勵數量 |
| max_uses | unsigned int, nullable | 全部會員合計的兌換次數上限，空值代表不限 |
| is_active | boolean, default true | |
| starts_at、ends_at | timestamp, nullable | 可兌換期間 |

### 3.4 `carrot_transactions`

| 欄位 | 型別 | 說明 |
|---|---|---|
| id | uuid | |
| member_id | FK members | |
| amount | integer | 增減數量，取消與扣除為負數 |
| reason | string(40) | 見下表 |
| note | string, nullable | 手動調整的原因 |
| task_id | FK carrot_tasks, nullable | 現場任務相關的紀錄 |
| authorization_code_id | FK authorization_codes, nullable | 授權碼相關的紀錄 |
| created_by | unsigned bigint, nullable | 手動調整與取消的管理員 |
| dedupe_key | string(120), unique | 防止重複的鍵 |

`reason` 的值：站上任務為任務代碼，`task` 為完成現場任務，`task_revoked` 為取消現場任務，`code` 為兌換授權碼，`manual` 為手動調整。

---

## 4. 後台管理

在「會員」群組：

| 資源 | 說明 |
|---|---|
| 胡蘿蔔任務 | 建立、編輯現場任務，詳情頁有 QR Code、掃描網址與短碼，列表顯示完成人次 |
| 授權碼 | 建立、編輯授權碼，列表顯示已兌換次數 |
| 胡蘿蔔明細 | 唯讀的全部紀錄，可搜尋會員、依原因篩選 |

會員詳情頁：

- 顯示目前的胡蘿蔔。
- 「調整胡蘿蔔」手動加減。
- 現場任務與站上任務的完成狀況，可以標記為已完成，或取消已完成的紀錄。
- 該會員的胡蘿蔔明細。

---

## 5. 測試

| 測試檔 | 涵蓋內容 |
|---|---|
| `tests/Feature/Carrots/` | 記帳規則、現場任務、任務頁、授權碼與短碼、後台管理 |
