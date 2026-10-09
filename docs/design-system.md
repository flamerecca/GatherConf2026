# GatherConf 官網 Design System

本文件整理 GatherConf 官網，也就是 `/` 與 `/en` 的 landing page 以及行為準則頁，目前實際使用的視覺語言，並補上之後新增頁面時應遵守的規則。內容依據為 `resources/css/app.css`、`resources/views/`、`resources/images/`、〈使命宣言〉、〈舉辦緣由〉與 `docs/screen-design-spec.md`。

> 狀態：初稿，已套用主辦團隊的決定，色票以 Logo 為準、支援 light 與 dark、間距只用偶數步階。第 11 節列出尚待處理的項目。

---

## 1. 品牌調性

GatherConf 想給人的感覺是「開放、包容、充滿活力的森林聚會」，不是冷冰冰的企業研討會。

| 關鍵字 | 在視覺上的意思 |
| :---- | :---- |
| **開放** | 大量留白、明亮的米白底色、寬鬆的行距，不堆疊資訊。 |
| **包容** | 溫暖的大地色系、圓角、人物與吉祥物，避免尖銳與強對比的裝飾。 |
| **有機** | 森林與水彩，取代科技產業常見的藍紫漸層與幾何圖案。 |
| **可信賴** | 資訊清楚、字級層次分明、數字與事實直接呈現，贊助商與參與者都讀得懂。 |

文字語氣：親切、直接、不浮誇。中文使用台灣正體中文，英文頁面使用簡單的現在式短句。

---

## 2. 品牌資源檔

| 資源 | 路徑 | 用途與規則 |
| :---- | :---- | :---- |
| **Logo** | `resources/images/GatherConf-Logo.png` | 圓形徽章，森林中一群人加上電路線，綠色字標。用於 Hero、頁尾與 favicon。在米白底上可使用 `mix-blend-multiply` 融入背景。不可拉伸、不可重新上色、周圍至少留 Logo 寬度 10% 的空間。 |
| **水彩插畫：共同參加年會** | `resources/images/community-gathering-watercolor.png` | 主視覺插畫，水彩手繪，描繪許多人在森林中共同參加年會。16:9，用於年會宗旨區塊。 |
| **水彩插畫：打破隔閡** | `resources/images/community-silos-watercolor.png` | 左半邊是被石牆與藤蔓隔開的安靜森林，右半邊是燈串下熱絡交流的聚會。約 2.4:1 的超寬構圖，用於「為什麼要舉辦」區塊。上方冷色、下方暖色的對比就是想傳達的訊息。 |
| **吉祥物 Bunny** | `resources/images/bunny/` | 扁平插畫風格的兔子，共 16 個造型。官網只在贊助方案區塊使用一個，不與水彩插畫同區塊並用。 |
| **NPC 插畫** | `resources/images/npc/` | 遊戲化內容專用，不用於官網 landing page。 |

插畫的共通規則：

- 畫面不放文字，這樣中英文頁面才能共用同一張圖。需要說明的文字放在圖下方的 HTML 標題。
- 色調需落在第 3 節色票的綠、米白、金、棕範圍內。
- 新圖片寬度至少 1600 px，檔名使用小寫加連字號，並以內容命名，例如 `community-gathering-watercolor.png`。

---

## 3. 色彩

色票以 GatherConf Logo 為準，並且是官網與 BBTI 測驗共用的唯一品牌色票。支援 light 與 dark 兩種模式，預設跟隨使用者的系統設定，也就是 `prefers-color-scheme`，頁首的切換按鈕可以手動覆寫，選擇會記在瀏覽器的 `localStorage`。

### 3.1 設計 Token

Token 定義在 `resources/css/app.css`。實際色值存放在 `:root` 的 `--c-*` 變數，dark 模式在系統為深色且使用者未選 light 時，或 `<html data-theme="dark">` 時覆寫，再由 `@theme inline` 對應成 Tailwind 的 `bg-*`、`text-*`、`fill-*` 等工具類別。因此元件只要使用語意化的類別，就會自動跟著模式切換，不需要加 `dark:` 前綴。

| Token | Light | Dark | 角色 |
| :---- | :---- | :---- | :---- |
| `parchment` | `#f4efe1` | `#121c15` | 頁面底色。Light 取自 Logo 的奶油色，dark 是帶綠的深色。 |
| `parchment-dark` | `#e8e1c9` | `#1c2a20` | 次要區塊底色，如統計卡片與贊助方案區塊。 |
| `ink` | `#1d3320` | `#ece6d3` | 主要文字，以及邊框與示意圖線條的基準色。 |
| `moss` | `#2b6a35` | `#7fc08a` | 品牌主色，用於主要按鈕背景、小標題與連結強調。 |
| `copper` | `#7d5812` | `#d4ad4f` | 頁面底色上的點綴色，如標籤與分隔符號。 |
| `gold` | `#d4ad4f` | `#d4ad4f` | 深色區塊上的點綴色，如引言邊線與頁尾連結。 |
| `deep` | `#1d3320` | `#213a28` | 深色區塊底色，如年會宗旨、頁尾與強調的贊助卡片。 |
| `on-deep` | `#f4efe1` | `#f4efe1` | 深色區塊上的文字。 |
| `on-accent` | `#f4efe1` | `#10190f` | 主要按鈕上的文字，放在 `moss` 背景上。 |

使用比例大致是：70% 底色，20% 墨綠與苔綠，10% 金類點綴。

為什麼要分出 `deep`、`on-deep` 與 `on-accent`：`ink` 與 `parchment` 在 dark 模式會互換，如果深色區塊直接用 `bg-ink text-parchment`，dark 模式就會變成淺色區塊。這三個 token 讓「深色區塊」與「按鈕文字」在兩種模式下都保持原本的角色。

### 3.2 不透明度

文字層級用 `ink` 加不透明度表達，不另外新增灰色。

| 用途 | 類別 |
| :---- | :---- |
| 主要文字 | `text-ink` |
| 次要文字 | `text-ink/80`、`text-ink/70`，最低 70% |
| 淺色邊框 | `border-ink/15`，裝飾用 |
| 深色區塊的主要文字 | `text-on-deep` |
| 深色區塊的次要文字 | `text-on-deep/80`、`text-on-deep/70`，最低 70% |

### 3.3 對比規則

以下為實測的對比值，標準採 WCAG AA，一般文字 4.5:1，大字與非文字的圖形 3:1。

| 組合 | Light | Dark | 可用於 |
| :---- | :---- | :---- | :---- |
| `ink` 在 `parchment` 上 | 11.8:1 | 14.0:1 | 所有文字 |
| `ink/70` 在 `parchment` 上 | 4.9:1 | 7.4:1 | 所有文字，70% 是下限 |
| `ink/70` 在 `parchment-dark` 上 | 4.6:1 | 6.7:1 | 所有文字，70% 是下限 |
| `moss` 在 `parchment` 上 | 5.7:1 | 8.2:1 | 所有文字 |
| `moss` 在 `parchment-dark` 上 | 5.0:1 | 7.0:1 | 所有文字 |
| `on-accent` 在 `moss` 上 | 5.7:1 | 8.4:1 | 按鈕文字 |
| `copper` 在 `parchment` 上 | 5.6:1 | 8.2:1 | 所有文字，包含小字標籤 |
| `copper` 在 `parchment-dark` 上 | 4.9:1 | 7.1:1 | 所有文字 |
| `gold` 在 `deep` 上 | 6.4:1 | 5.8:1 | 所有文字 |
| `on-deep` 在 `deep` 上 | 11.8:1 | 10.8:1 | 所有文字 |
| `on-deep/70` 在 `deep` 上 | 6.6:1 | 6.2:1 | 所有文字 |

規則：
- `gold` 只用在 `deep` 區塊上，在 light 的頁面底色上對比只有 1.9:1，只能作為邊線或背景裝飾，不可用於文字。
- `copper` 只用在頁面底色上，不可用在 `deep` 區塊的文字。
- 色彩不是唯一的資訊管道，插畫旁一律有文字標題說明意思。

### 3.4 深淺色切換

- **按鈕**：`<x-theme-toggle />` 元件，圓形外框按鈕，light 時顯示月亮圖示，dark 時顯示太陽圖示，表示「點了會變成的模式」。放在頁首右上角，語言切換按鈕的左邊。
- **狀態**：以 `<html data-theme="light|dark">` 表示。`<head>` 內的行內腳本在頁面繪製前就設定好，避免閃爍。沒有儲存的選擇時，跟隨系統設定。
- **儲存**：選擇存在 `localStorage` 的 `theme` 鍵，儲存失敗時，如隱私模式，僅本次瀏覽生效。
- **`dark:` 變體**：已改為依 `data-theme` 判斷，不是系統設定，所以手動切換時 `dark:` 類別也會同步生效。
- **無障礙**：按鈕有 `aria-label`，文案放在語言檔的 `theme_toggle`，圖示本身 `aria-hidden`。

### 3.5 dark 模式的處理

- 淺色背景的圖片需要特別處理。Logo 與 Bunny 在 light 使用 `mix-blend-multiply` 融入底色，dark 時改為 `dark:mix-blend-normal`，並以圓角裁切，避免白底在深色頁面上消失。
- 水彩插畫不做反色，直接以圓角與陰影呈現。
- 陰影在 dark 模式不明顯，層級區隔依靠底色差異 `parchment` 與 `parchment-dark`，不要依賴陰影。
- 頁面 `<head>` 加上 `<meta name="color-scheme" content="light dark">`，讓瀏覽器的捲軸與表單元件也跟著切換。

---

## 4. 字體

| 項目 | 規則 |
| :---- | :---- |
| **字型** | `font-sans`，即 Instrument Sans，後備為系統無襯線字體。中文由系統字體負責，如蘋方與微軟正黑體。 |
| **字重** | 標題 `font-bold`，小標題與強調 `font-semibold`，數字統計 `font-bold`，內文使用預設。 |
| **行距** | 內文一律 `leading-relaxed`。 |
| **中英混排** | 中文與英文、數字之間保留一格半形空格。 |

字級階層：

| 層級 | 類別 | 用途 |
| :---- | :---- | :---- |
| 頁面標題 H1 | `text-5xl md:text-6xl font-bold tracking-tight` | 只在 Hero 與行為準則頁出現，每頁一個。 |
| 區塊標題 H2 | `text-3xl font-bold` | 每個主要區塊的標題。 |
| 行為準則段落標題 | `text-2xl font-bold text-moss` | 長文頁面的段落標題。 |
| 卡片標題 H3 | `text-2xl font-bold`，小卡片用 `font-semibold` | 贊助層級與說明卡片。 |
| 引導文字 | `text-xl` 到 `text-lg` | Hero 副標與區塊開頭。 |
| 內文 | 預設 `text-base` | 一般段落。 |
| 標籤 Eyebrow | `text-sm font-semibold tracking-widest uppercase` | 區塊上方的英文小標，如「GatherConf 2026」。 |
| 說明文字 | `text-sm` | 圖說、英文引言。 |

---

## 5. 間距、圓角與陰影

### 5.1 間距

延續 `docs/screen-design-spec.md` 的 8px 網格約定，官網也遵守同一套規則，現有程式碼已全部調整完成。

- 間距只用偶數步階：`2`、`4`、`6`、`8`、`12`、`16`、`20`、`24`，分別是 8、16、24、32、48、64、80、96 px。
- 奇數步階與半步階，如 `gap-3`、`py-1`、`mt-10`、`gap-1.5`，一律不使用。想用 `3` 就改 `4`，想用 `10` 就改 `12`，想用 `1` 就改 `2`。
- 這條規則只管間距，也就是 `p-*`、`m-*`、`gap-*`、`space-*`。尺寸類別如 `size-3` 的色點與 `h-40` 不受限制。
- 兄弟元件之間用 `gap-*`，不要疊加 margin。
- 區塊上下留白 `py-16`，頁尾 `py-12`。
- 內容欄寬：landing page 為 `max-w-6xl`，長文閱讀區為 `max-w-3xl`，欄位左右留白 `px-4`。

### 5.2 圓角

| 類別 | 用途 |
| :---- | :---- |
| `rounded-2xl` | 卡片、圖片、區塊內的容器，全站預設。 |
| `rounded-full` | 按鈕、語言切換、Logo 圓形圖示、色點。 |

不使用 `rounded-sm` 或無圓角，維持圓潤、柔和的感覺。

### 5.3 陰影

只用兩種，並且只用在需要被視為「浮起」的元素。

| 類別 | 用途 |
| :---- | :---- |
| `shadow` | 主要按鈕。 |
| `shadow-xl` | 深色區塊上的大圖，與強調的贊助卡片。 |

其他元件以邊框 `border-ink/15` 或底色差異區隔，不加陰影。

---

## 6. 版面與響應式

- **行動裝置優先**：先設計單欄，再用 `md:` 斷點，也就是 768 px，展開成多欄。
- **斷點**：官網只使用 `md:`，需要時才加 `sm:`，如隱藏吉祥物，不使用 `lg:` 以上的複雜變化。
- **區塊節奏**：淺色與深色區塊交錯，帶出段落感。

| 順序 | 區塊 | 底色 |
| :---- | :---- | :---- |
| 1 | Hero | `parchment` |
| 2 | 年會宗旨 | `deep` |
| 3 | 為什麼要舉辦 | `parchment` |
| 4 | 年會概述 | `parchment` |
| 5 | 贊助方案 | `parchment-dark` |
| 6 | 頁尾 | `deep` |

- **網格**：2 欄與 3 欄為主，如統計卡片 `grid-cols-2 md:grid-cols-4`，贊助方案 `md:grid-cols-3`。窄螢幕一律單欄堆疊。
- **圖片**：一律設定寬度為容器 100%，插畫保持原始比例，不以裁切掉主體的方式填滿。

---

## 7. 元件

所有元件以 Blade 與 Tailwind 工具類別組成，沒有額外的 CSS 檔。新增相同用途的元件時，請沿用下列組合。

### 7.1 主要按鈕

```html
<a class="inline-block rounded-full bg-moss px-8 py-3 text-lg font-semibold text-on-accent shadow transition hover:bg-ink">
```

- 每個畫面只放一個主要行動，目前是「立即報名」。
- 外部連結加上 `target="_blank" rel="noopener"`。

### 7.2 次要按鈕與語言切換

```html
<a class="rounded-full border border-ink/30 px-4 py-1 text-sm font-medium transition hover:bg-ink hover:text-parchment">
```

### 7.3 資訊卡片

淺色底：

```html
<div class="rounded-2xl border border-ink/15 p-6">
```

強調型與統計卡片使用 `bg-parchment-dark`，不加邊框：

```html
<div class="rounded-2xl bg-parchment-dark p-6 text-center">
```

### 7.4 贊助層級卡片

- 第一個層級，也就是金獎級，使用深色強調：`bg-deep text-on-deep shadow-xl`。
- 其餘層級使用 `border border-ink/15 bg-parchment`。
- 內容順序固定：英文層級名、中文層級名、金額、權益清單。
- 權益清單前綴以 `✦` 表示，一般卡片用 `copper`，深色卡片用 `gold`。

### 7.5 引言與重點句

```html
<p class="border-l-4 border-gold pl-4 text-lg">
```

用於區塊結尾的一句話。深色底使用 `border-gold`，淺色底使用 `border-gold` 亦可，因為邊線屬於裝飾。

### 7.6 標籤與資訊列表

- 時間、地點使用 `dl`，名稱用 `font-semibold text-moss`，內容為預設色。
- 圖例使用 `size-3 rounded-full` 的色點，加上文字。

### 7.7 圖與圖說

- 主視覺插畫：`aspect-video w-full rounded-2xl object-cover shadow-xl`。
- 對照型插畫，如打破隔閡：`w-full rounded-2xl shadow-xl`，保留原始比例不裁切。下方的 `figcaption` 用兩欄 `grid-cols-2` 對應圖的左右兩半，右半邊標題使用 `text-moss`。
- 若需要以 SVG 繪製示意圖，色彩一律使用 token 類別，如 `fill-moss`、`stroke-ink/30`，不寫死色碼，這樣才會跟著 dark 模式切換。

### 7.8 頁尾

深色底 `bg-deep`，置中，依序是圓形 Logo、活動名稱、日期與地點、連結列。連結一律使用 `text-gold underline`。

---

## 8. 圖示與裝飾

- 清單前綴使用 `✦`，作為唯一的裝飾符號。
- 流程箭頭用 `→`，窄螢幕換成 `↓`。
- 裝飾符號加上 `aria-hidden="true"`。
- 目前不引入圖示函式庫。需要圖示時，先與主辦團隊討論，並優先使用單色線條風格。

---

## 9. 雙語與文案規則

| 項目 | 規則 |
| :---- | :---- |
| **路由** | 中文 `/`，英文 `/en`。子頁面對應 `/code-of-conduct` 與 `/en/code-of-conduct`。 |
| **文案位置** | 所有可見文字放在 `lang/zh_TW/` 與 `lang/en/`，Blade 以 `__('檔名.鍵')` 讀取。不要把文案寫死在 view。 |
| **語言切換** | 每頁頁首右上角放切換按鈕，標籤以「目標語言的原文」顯示，也就是中文頁顯示 `English`，英文頁顯示 `繁體中文`。 |
| **圖片** | 圖片內不放文字，替代文字兩種語言各自提供。 |
| **中文用語** | 遵守台灣正體中文用語，如「軟體」、「資料」、「品質」，避免「落地」、「颗粒度」等中國大陸用語。 |
| **標點** | 中文使用全形標點與「」引號，避免括號補充說明。 |
| **按鈕文字** | 用動詞開頭的短句，如「立即報名」、`Register now`。 |
| **內部資訊** | 預算、建議名額、內部估算等資訊不放上官網。 |

---

## 10. 無障礙

- 所有圖片提供替代文字，裝飾性圖形加 `aria-hidden`。
- 文字與底色的對比遵守第 3.3 節，light 與 dark 兩種模式都要達標。
- 互動元件需要可見的焦點樣式，使用瀏覽器預設的 focus ring，不以 `outline-none` 移除。
- 頁面語言以 `<html lang>` 標示，中文為 `zh-Hant-TW`，英文為 `en`。
- 標題層級依序使用，每頁一個 H1。
- 動畫目前只有按鈕底色的 `transition`，若日後新增動態效果，需尊重 `prefers-reduced-motion`。

---

## 11. 決定紀錄與待辦事項

### 已決定並完成

| # | 項目 | 決定 |
| :---- | :---- | :---- |
| 1 | `/` 路由 | `/` 維持為官網 landing page。BBTI 測驗導引頁另外使用新路徑，路徑待主辦團隊提供。 |
| 2 | 色票 | 以取自 Logo 的官網色票為準，BBTI 之後沿用。 |
| 3 | 深色模式 | 官網支援 light 與 dark，預設跟隨系統設定，並提供手動切換按鈕。 |
| 4 | 8px 網格 | 官網只使用偶數步階，現有程式碼已調整。 |
| 5 | 小字對比 | 淺色模式的 `copper` 由 `#9a6f1e` 改為 `#7d5812`，小字標籤對比由 3.9:1 提升到 5.6:1。 |

### 待辦

| # | 項目 | 說明 | 建議 |
| :---- | :---- | :---- | :---- |
| 1 | **BBTI 導引頁路徑** | 等主辦團隊提供新路徑後，需要更新 `docs/screen-design-spec.md` 與規劃書裡的 `/`。 | 路徑確定後一併更新路由與文件。 |
| 2 | **BBTI 色票對齊** | `docs/screen-design-spec.md` 的色票仍是舊值，如 `#F5F1E8` 與 `#2F6B4F`。 | 改成本文件第 3 節的 token，並重新驗證四個維度識別色在新的底色上的對比。 |
| 3 | **插畫風格並存** | 官網主視覺是水彩，BBTI 吉祥物是扁平插畫。 | 同一頁面不混用，水彩用於情境，吉祥物用於角色與測驗。 |
| 4 | **贊助商無聯絡方式** | 贊助區沒有行動按鈕。 | 提供聯絡 email 或表單後，加上次要按鈕。 |
| 5 | **水彩插畫解析度** | 為 1024 px 寬，桌機會被放大。 | 重新生成 1600 px 以上的版本。 |
| 6 | **dark 模式視覺檢查** | 對比已用數值驗證，但尚未實際在瀏覽器逐頁檢視。 | 在系統切換到深色模式後，檢查 Logo、吉祥物與水彩插畫的呈現。 |

---

## 12. 新增頁面時的檢查清單

1. 版面繼承 `layouts/app.blade.php`，並設定 `title` 與 `description`。
2. 所有文字放進 `lang/zh_TW/` 與 `lang/en/`，並建立對應的中英文路由。
3. 只使用第 3 節的 token，不新增色碼，也不寫死色碼。需要深色區塊時用 `bg-deep text-on-deep`，不要用 `bg-ink text-parchment`。
4. 區塊上下留白 `py-16`，內容寬度 `max-w-6xl` 或 `max-w-3xl`。
5. 每頁一個 H1，並且只有一個主要按鈕。
6. 圖片不含文字，且附上兩種語言的替代文字。
7. 間距只用偶數步階。
8. 先在手機寬度確認單欄版面，再確認 `md:` 以上的樣子，並且分別在 light 與 dark 檢查。
9. 新增 PHPUnit feature test，驗證中英文頁面皆回傳 200 並包含關鍵文字。
