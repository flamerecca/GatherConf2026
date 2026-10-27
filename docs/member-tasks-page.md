# 會員任務頁 `/member/tasks`

本文件說明會員任務與胡蘿蔔頁面的版面與行為，對象為維護頁面的工程與營運人員。英文版路徑為 `/en/member/tasks`，需登入並已驗證 Email 才能進入。

---

## 1. 頁面結構

由上到下：

1. 頁首標題「任務與胡蘿蔔」。
2. 「← 會員頁面」按鈕，回到會員中心。
3. 目前的胡蘿蔔餘額，餘額為負數時以紅色粗體顯示。
4. 站上任務，分成「帳號任務」與「人格測驗與好友任務」兩組，標題都可以收合。
5. 年會現場任務，只列出目前開放的任務，任務代碼不會顯示在頁面上。

---

## 2. 頁首的「會員頁面」按鈕

- 位置在標題下方、餘額上方。
- 中文顯示「← 會員頁面」，英文顯示「← Member page」。
- 連到會員中心，中文是 `/member`，英文是 `/en/member`。
- 樣式為外框按鈕，和下方實心的「前往完成」按鈕區隔，避免兩者搶視覺權重。
- 文字定義在 `lang/zh_TW/carrots.php` 與 `lang/en/carrots.php` 的 `list.member_page`。

---

## 3. 站上任務的「前往完成」按鈕

每個尚未完成的站上任務，下方會有一個「前往完成」按鈕，英文版顯示「Go do it」，點擊後導向可以完成該任務的頁面：

| 任務 | 獎勵 | 導向 |
| :--- | :--- | :--- |
| 驗證 Email | 5 | 沒有按鈕，從註冊或登入信件中完成 |
| 設定密碼 | 3 | `/member/password` |
| 完成人格測驗 | 5 | `/quiz` |
| 交到第一位好友 | 5 | `/friends` |
| 傳出第一則訊息 | 2 | `/friends` |

- 英文版導向對應的 `/en/...` 頁面。
- 「傳出第一則訊息」導向好友頁，因為訊息只能傳給好友，也沒有獨立的訊息列表頁，使用者要在好友頁挑一位好友開啟對話。
- 已完成的任務不顯示按鈕，狀態標示為「已完成」。
- 獎勵點數定義在 `config/carrots.php` 的 `site`。
- 任務與導向頁面的對照寫在 `resources/views/member/tasks.blade.php` 的 `$siteTaskRoutes`。

---

## 4. 新增或調整站上任務

1. 在 `config/carrots.php` 的 `site` 加入任務代碼與獎勵點數。
2. 在 `lang/zh_TW/carrots.php` 與 `lang/en/carrots.php` 的 `site` 加入該任務的 `name` 與 `description`。
3. 在 `resources/views/member/tasks.blade.php` 的 `$groups` 把代碼放進對應的群組。
4. 需要導向按鈕時，在同一個檔案的 `$siteTaskRoutes` 加入代碼與路由名稱，不含 `.en` 後綴，英文版會自動補上。
5. 在 `tests/Feature/Carrots/TaskListTest.php` 補上對應的斷言。
