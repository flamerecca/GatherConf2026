# GatherConf 官網 Design System

本文件整理 GatherConf 官網目前實際使用的視覺語言，範圍包含 landing page、行為準則頁與合作社群頁，網址對照見第 9 節，並補上之後新增頁面時應遵守的規則。內容依據為 `resources/css/app.css`、`resources/views/`、`resources/images/`、`config/`、`lang/`、〈使命宣言〉、〈舉辦緣由〉與 `docs/screen-design-spec.md`。

> 狀態：初稿，已套用主辦團隊的決定，色票以 Logo 為準、支援 light 與 dark、間距只用偶數步階。第 11 節列出尚待處理的項目。

---

## 1. 品牌調性

GatherConf 想給人的感覺是「開放、包容、充滿活力的森林聚會」，不是冷冰冰的企業研討會。

| 關鍵字     | 在視覺上的意思                                                       |
|:-----------|:---------------------------------------------------------------------|
| **開放**   | 大量留白、明亮的米白底色、寬鬆的行距，不堆疊資訊。                   |
| **包容**   | 溫暖的大地色系、圓角、人物與吉祥物，避免尖銳與強對比的裝飾。         |
| **有機**   | 森林與水彩，取代科技產業常見的藍紫漸層與幾何圖案。                   |
| **可信賴** | 資訊清楚、字級層次分明、數字與事實直接呈現，贊助商與參與者都讀得懂。 |

文字語氣：親切、直接、不浮誇。中文使用台灣正體中文，英文頁面使用簡單的現在式短句。

---

## 2. 品牌資源檔

| 資源                       | 路徑                                                  | 用途與規則                                                                                                                                                                       |
|:---------------------------|:------------------------------------------------------|:---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Logo**                   | `resources/images/GatherConf-Logo.png`                | 圓形徽章，森林中一群人加上電路線，綠色字標。用於 Hero、頁尾與 favicon。在米白底上可使用 `mix-blend-multiply` 融入背景。不可拉伸、不可重新上色、周圍至少留 Logo 寬度 10% 的空間。 |
| **水彩插畫：共同參加年會** | `resources/images/community-gathering-watercolor.png` | 主視覺插畫，水彩手繪，描繪許多人在森林中共同參加年會。16:9，用於年會宗旨區塊。                                                                                                   |
| **水彩插畫：打破隔閡**     | `resources/images/community-silos-watercolor.png`     | 左半邊是被石牆與藤蔓隔開的安靜森林，右半邊是燈串下熱絡交流的聚會。約 2.4:1 的超寬構圖，用於「為什麼要舉辦」區塊。上方冷色、下方暖色的對比就是想傳達的訊息。                      |
| **吉祥物 Bunny**           | `resources/images/bunny/`                             | 扁平插畫風格的兔子，共 16 個造型。官網只在贊助方案區塊使用一個，不與水彩插畫同區塊並用。                                                                                         |
| **NPC 插畫**               | `resources/images/npc/`                               | 遊戲化內容專用，不用於官網 landing page。                                                                                                                                        |
| **合作社群 Logo**          | `resources/images/community-logo/`                    | 各合作社群提供的 Logo，檔名為社群簡稱，如 `TKUG.png`。用於首頁合作社群區塊與合作社群頁。                                                                                         |
| **贊助商 Logo**            | `resources/images/sponsor-logo/`                      | 各贊助商提供的 Logo，檔名為公司或品牌的英文小寫連字號，如 `rabbit-gather.png`。用於首頁贊助商區塊。                                                                              |

插畫的共通規則：

- 畫面不放文字，這樣中英文頁面才能共用同一張圖。需要說明的文字放在圖下方的 HTML 標題。
- 色調需落在第 3 節色票的綠、米白、金、棕範圍內。
- 合作社群與贊助商的 Logo 由對方提供，不重新上色、不變形。一律裁成圓形並加外框線，規則見 7.8，這樣 light 與 dark 模式才都看得清楚。
- 新圖片寬度至少 1600 px，檔名使用小寫加連字號，並以內容命名，例如 `community-gathering-watercolor.png`。

---

## 3. 色彩

色票以 GatherConf Logo 為準，並且是官網與 BBTI 測驗共用的唯一品牌色票。支援 light 與 dark 兩種模式，預設跟隨使用者的系統設定，也就是 `prefers-color-scheme`，頁首的切換按鈕可以手動覆寫，選擇會記在瀏覽器的 `localStorage`。

### 3.1 設計 Token

Token 定義在 `resources/css/app.css`。實際色值存放在 `:root` 的 `--c-*` 變數，dark 模式在系統為深色且使用者未選 light 時，或 `<html data-theme="dark">` 時覆寫，再由 `@theme inline` 對應成 Tailwind 的 `bg-*`、`text-*`、`fill-*` 等工具類別。因此元件只要使用語意化的類別，就會自動跟著模式切換，不需要加 `dark:` 前綴。

| Token            | Light     | Dark      | 角色                                                    |
|:-----------------|:----------|:----------|:--------------------------------------------------------|
| `parchment`      | `#f4efe1` | `#121c15` | 頁面底色。Light 取自 Logo 的奶油色，dark 是帶綠的深色。 |
| `parchment-dark` | `#e8e1c9` | `#1c2a20` | 次要區塊底色，如統計卡片與贊助方案區塊。                |
| `ink`            | `#1d3320` | `#ece6d3` | 主要文字，以及邊框與示意圖線條的基準色。                |
| `moss`           | `#2b6a35` | `#7fc08a` | 品牌主色，用於主要按鈕背景、小標題與連結強調。          |
| `copper`         | `#7d5812` | `#d4ad4f` | 頁面底色上的點綴色，如標籤與分隔符號。                  |
| `gold`           | `#d4ad4f` | `#d4ad4f` | 深色區塊上的點綴色，如引言邊線與頁尾連結。              |
| `deep`           | `#1d3320` | `#213a28` | 深色區塊底色，如年會宗旨與頁尾。                        |
| `on-deep`        | `#f4efe1` | `#f4efe1` | 深色區塊上的文字。                                      |
| `on-accent`      | `#f4efe1` | `#10190f` | 主要按鈕上的文字，放在 `moss` 背景上。                  |
| `tier-gold`      | `#9c7000` | `#e6c35c` | 金獎級的邊框與層級圓點，贊助方案與贊助商共用。          |
| `tier-silver`    | `#6f7a85` | `#c3cbd3` | 銀獎級的邊框與層級圓點，贊助方案與贊助商共用。          |
| `tier-bronze`    | `#9a5524` | `#d9843a` | 銅獎級的邊框與層級圓點，贊助方案與贊助商共用。          |

使用比例大致是：70% 底色，20% 墨綠與苔綠，10% 金類點綴。

為什麼要分出 `deep`、`on-deep` 與 `on-accent`：`ink` 與 `parchment` 在 dark 模式會互換，如果深色區塊直接用 `bg-ink text-parchment`，dark 模式就會變成淺色區塊。這三個 token 讓「深色區塊」與「按鈕文字」在兩種模式下都保持原本的角色。

### 3.2 不透明度

文字層級用 `ink` 加不透明度表達，不另外新增灰色。

| 用途               | 類別                                           |
|:-------------------|:-----------------------------------------------|
| 主要文字           | `text-ink`                                     |
| 次要文字           | `text-ink/80`、`text-ink/70`，最低 70%         |
| 淺色邊框           | `border-ink/15`，裝飾用                        |
| 深色區塊的主要文字 | `text-on-deep`                                 |
| 深色區塊的次要文字 | `text-on-deep/80`、`text-on-deep/70`，最低 70% |

### 3.3 對比規則

以下為實測的對比值，標準採 WCAG AA，一般文字 4.5:1，大字與非文字的圖形 3:1。

| 組合                                 | Light  | Dark   | 可用於                 |
|:-------------------------------------|:-------|:-------|:-----------------------|
| `ink` 在 `parchment` 上              | 11.8:1 | 14.0:1 | 所有文字               |
| `ink/70` 在 `parchment` 上           | 4.9:1  | 7.4:1  | 所有文字，70% 是下限   |
| `ink/70` 在 `parchment-dark` 上      | 4.6:1  | 6.7:1  | 所有文字，70% 是下限   |
| `moss` 在 `parchment` 上             | 5.7:1  | 8.2:1  | 所有文字               |
| `moss` 在 `parchment-dark` 上        | 5.0:1  | 7.0:1  | 所有文字               |
| `on-accent` 在 `moss` 上             | 5.7:1  | 8.4:1  | 按鈕文字               |
| `copper` 在 `parchment` 上           | 5.6:1  | 8.2:1  | 所有文字，包含小字標籤 |
| `copper` 在 `parchment-dark` 上      | 4.9:1  | 7.1:1  | 所有文字               |
| `gold` 在 `deep` 上                  | 6.4:1  | 5.8:1  | 所有文字               |
| `on-deep` 在 `deep` 上               | 11.8:1 | 10.8:1 | 所有文字               |
| `on-deep/70` 在 `deep` 上            | 6.6:1  | 6.2:1  | 所有文字               |
| `tier-gold` 在 `parchment-dark` 上   | 3.4:1  | 8.8:1  | 僅限邊框與圖形         |
| `tier-silver` 在 `parchment-dark` 上 | 3.3:1  | 9.1:1  | 僅限邊框與圖形         |
| `tier-bronze` 在 `parchment-dark` 上 | 4.3:1  | 5.2:1  | 僅限邊框與圖形         |

規則：
- `gold` 只用在 `deep` 區塊上，在 light 的頁面底色上對比只有 1.9:1，只能作為邊線或背景裝飾，不可用於文字。
- `copper` 只用在頁面底色上，不可用在 `deep` 區塊的文字。
- 層級色 `tier-*` 只用於邊框與圓點，不用於文字。
- 色彩不是唯一的資訊管道，插畫旁一律有文字標題說明意思。贊助商的層級除了邊框顏色，還有分組標題的文字。

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

| 項目         | 規則                                                                                            |
|:-------------|:------------------------------------------------------------------------------------------------|
| **字型**     | `font-sans`，即 Instrument Sans，後備為系統無襯線字體。中文由系統字體負責，如蘋方與微軟正黑體。 |
| **字重**     | 標題 `font-bold`，小標題與強調 `font-semibold`，數字統計 `font-bold`，內文使用預設。            |
| **行距**     | 內文一律 `leading-relaxed`。                                                                    |
| **中英混排** | 中文與英文、數字之間保留一格半形空格。                                                          |

字級階層：

| 層級 | 類別 | 用途 |
| :---- | :---- | :---- |
| 頁面標題 H1 | `text-5xl md:text-6xl font-bold tracking-tight` | 只在 Hero、行為準則頁與合作社群頁出現，每頁一個。 |
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
| `shadow-xl` | 深色區塊上的大圖、水彩插畫，以及滑過贊助商卡片時的浮起效果。 |

其他元件以邊框 `border-ink/15` 或底色差異區隔，不加陰影。

---

## 6. 版面與響應式

- **行動裝置優先**：先設計單欄，再用 `md:` 斷點，也就是 768 px，展開成多欄。
- **斷點**：官網主要使用 `md:`，需要時加 `sm:`，如隱藏吉祥物與社群卡片的兩欄排列，不使用 `lg:` 以上的複雜變化。
- **區塊節奏**：淺色與深色區塊交錯，帶出段落感。

| 順序 | 區塊 | 底色 |
| :---- | :---- | :---- |
| 1 | Hero | `parchment` |
| 2 | 年會宗旨 | `deep` |
| 3 | 為什麼要舉辦 | `parchment` |
| 4 | 年會概述 | `parchment` |
| 5 | 合作社群 | `parchment` |
| 6 | 贊助方案 | `parchment-dark` |
| 7 | 贊助商 | `parchment` |
| 8 | 頁尾 | `deep` |

- **網格**：統計卡片 `grid-cols-2 md:grid-cols-4`，贊助方案 `md:grid-cols-3`，首頁合作社群 `sm:grid-cols-2 md:grid-cols-4`，合作社群頁 `sm:grid-cols-2 md:grid-cols-3`。窄螢幕一律單欄堆疊。贊助商依層級各自一排，用 `flex flex-wrap` 排列。
- **圖片**：一律設定寬度為容器 100%，插畫保持原始比例，不以裁切掉主體的方式填滿。

---

## 7. 元件

所有元件以 Blade 與 Tailwind 工具類別組成，沒有額外的 CSS 檔。新增相同用途的元件時，請沿用下列組合。

### 7.1 主要按鈕

```html
<a class="inline-block rounded-full bg-moss px-8 py-4 text-lg font-semibold text-on-accent shadow transition hover:bg-ink">
```

- 每個畫面只放一個主要行動，目前是「立即報名」。
- 外部連結加上 `target="_blank" rel="noopener"`。

### 7.2 次要按鈕、語言切換與頁首連結

```html
<a class="rounded-full border border-ink/30 px-4 py-2 text-sm font-medium transition hover:bg-ink hover:text-parchment">
```

- 首頁頁首導覽列由左至右是：行為準則文字連結 `text-sm font-medium underline-offset-4 hover:underline`、深淺色切換按鈕、語言切換按鈕。
- 子頁面的頁首使用 `<x-subpage-header>` 元件，左邊是 Logo 與「回到首頁」，右邊是深淺色切換與語言切換。傳入 `switch-url`、`switch-label` 與 `back-label` 三個屬性。

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

贊助方案與贊助商共用同一套層級視覺，讀者在方案區看到的顏色，會在贊助商區對應到相同的等級。

- 三張卡片外觀一致：`rounded-2xl border-4 bg-parchment p-8`，邊框顏色依層級使用 `border-tier-gold`、`border-tier-silver` 或 `border-tier-bronze`。不再以深色底強調單一層級，等級由邊框顏色與金額區分。
- 內容順序固定：英文層級名加同色圓點、中文層級名、金額、權益清單。
- 英文層級名前的圓點為 `size-3 rounded-full bg-tier-*`。
- 權益清單前綴以 `✦` 表示，使用 `copper`。
- 金額以語言檔的 `price` 格式輸出，中文頁為 `NT$ 20,000`，英文頁為 `€500`。金額本身放在各語言檔的 `tiers`，兩種語言各自維護，不做自動換算。
- 目前金額，中文頁為 NT$ 20,000、10,000 與 5,000，英文頁為 €500、250 與 125。

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

### 7.8 圓形 Logo

合作社群與贊助商的 Logo 一律裁成圓形，由 `<x-circle-logo>` 元件統一處理。對方提供的 Logo 背景色不一，如果放在方形白色底板上，淺灰或米色的背景會和白色底板形成色差，圓形配合填滿可以消除這個問題。

```html
<x-circle-logo :src="..." :alt="..." fit="cover|contain" :padding="15" size="size-40" />
```

- 外觀：`rounded-full ring-1 ring-ink/15`，淡淡的外框線讓圓形的邊緣在 light 與 dark 模式下都清楚。
- 屬性 `size`：圓的直徑，預設 `size-40`。贊助商依層級傳入 `size-48`、`size-40`、`size-32`。
- 屬性 `fit`：資料中的 `logo_fit`，決定圖片在圓裡的處理方式。
- 屬性 `padding`：資料中的 `logo_padding`，只在 `contain` 時有效，是內縮的百分比，預設 15。數字越小，Logo 越大。

| `fit`     | 處理方式                                           | 適用的 Logo                                                               |
|:----------|:---------------------------------------------------|:--------------------------------------------------------------------------|
| `cover`   | 圖片鋪滿整個圓，`object-cover`。                   | 本身就是圓形構圖，或背景是單色的 Logo。背景色會一路填到圓邊，不會有色差。 |
| `contain` | 圖片縮小置中，白色底，內縮 `logo_padding` 百分比。 | 方形或橫向的 Logo。內縮的目的是讓內容留在圓內，不被裁掉。                 |

**如何決定內縮百分比**

圓形會裁掉圖片的四個角，所以要先量出 Logo 內容離圖片中心最遠的距離，換算成圖片寬度的比例，稱為內容半徑 r。圓的半徑是 0.5，內容縮小後的半徑是 `r × (1 − 2 × 內縮)`。目標是讓縮小後的半徑約 0.44，留一點邊距，因此：

內縮 = `(1 − 0.44 ÷ r) ÷ 2`，算出來是負數就用 0。

預設 15% 最保險，但多數 Logo 會顯得偏小。放大前一定要先量測，量測方法是把圖片縮成 500 px 見方，用 ImageMagick 找出非白色像素，計算每個像素到中心的最大距離。

目前各 Logo 的量測值與設定：

| Logo | 內容半徑 r | `logo_padding` | 縮小後半徑 |
| :---- | :---- | :---- | :---- |
| Taiwan Kotlin User Group | 0.518 | 8 | 0.435 |
| Taiwan Obsidian User Group | 0.434 | 0 | 0.434 |
| GDG 桃園 | 0.401 | 0 | 0.401 |
| iPlayground | 0.394 | 0 | 0.394 |
| 人本與科技共融發展協會 | 0.298 | 0 | 0.298 |
| WordPress Taiwan | 0.387 | 0 | 0.387 |
| 兔給得資訊有限公司，贊助商 | 未量測 | 預設 15 | 未量測 |

TKUG 的內容半徑超過 0.5，也就是原圖的內容本來就會被圓形裁到邊緣，所以不能用 0。

注意：替換 Logo 檔案後，構圖若改變，需要重新量測。

### 7.9 社群卡片

`<x-community-card :community="$community" />`，首頁合作社群區塊與合作社群頁共用。傳入 `show-description` 時，在名稱下方顯示一段簡短說明，只有合作社群頁使用。

- 整張卡片是連結，在新分頁開啟，`rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6`，邊框粗細與贊助方案卡片一致，滑過時邊框變為 `moss`。
- 內容由上而下：7.8 的圓形 Logo `size-40`、依語言顯示的社群名稱 `text-lg font-semibold`、簡短說明 `text-sm text-ink/70`，僅限合作社群頁、「前往社群網站」`text-sm text-moss`。
- 資料來自 `config/communities.php`，名稱需同時提供 `zh_TW` 與 `en`，沒有中文名稱時，兩種語言填同一個名稱，並指定 `logo_fit`。

### 7.10 贊助商卡片

首頁贊助商區塊，位於贊助方案之後，層級視覺與 7.4 相同，Logo 規則見 7.8，依金、銀、銅獎級分組，只顯示有贊助商的層級。

- 分組標題：`text-sm font-semibold tracking-widest text-copper uppercase`，前面有一顆同層級顏色的 `size-3` 圓點。
- 卡片外框 `border-4`，顏色依層級使用 `border-tier-gold`、`border-tier-silver` 或 `border-tier-bronze`，讓讀者一眼看出等級。
- Logo 使用 7.8 的圓形元件，大小依層級遞減，金獎級 `size-48`，銀獎級 `size-40`，銅獎級 `size-32`。
- 內容由上而下：Logo、依語言顯示的公司名稱、「前往官網」。
- 沒有任何贊助商時，整個區塊不輸出。
- 資料來自 `config/sponsors.php`，名稱需同時提供 `zh_TW` 與 `en`，並指定 `logo_fit`。

### 7.11 頁尾

深色底 `bg-deep`，置中，依序是圓形 Logo、活動名稱、日期與地點、連結列。連結列包含「立即報名」、「行為準則」與「合作社群」，一律使用 `text-gold underline`，空間不足時自動換行。

---

## 8. 圖示與裝飾

- 清單前綴使用 `✦`，作為唯一的裝飾符號。
- 流程箭頭用 `→`，窄螢幕換成 `↓`。
- 裝飾符號加上 `aria-hidden="true"`。
- 目前不引入圖示函式庫。需要圖示時，先與主辦團隊討論，並優先使用單色線條風格。

---

## 9. 雙語與文案規則

| 項目         | 規則                                                                                                                                                                                                                                          |
|:-------------|:----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **路由**     | 中文在前、英文以 `/en` 開頭。首頁 `/` 與 `/en`，行為準則 `/code-of-conduct` 與 `/en/code-of-conduct`，合作社群 `/communities` 與 `/en/communities`。路由名稱為 `landing`、`conduct`、`communities`，英文版加 `.en`。BBTI 導引頁保留 `/bbti`。 |
| **貨幣**     | 中文頁使用新台幣，格式 `NT$ 20,000`。英文頁使用歐元，格式 `€500`。                                                                                                                                                                            |
| **專有名詞** | 公司與社群名稱依語言顯示，英文頁使用正式英文全名，如 Rabbit Gather Co., Ltd.。沒有英文名稱時，才沿用原文。                                                                                                                                    |
| **文案位置** | 所有可見文字放在 `lang/zh_TW/` 與 `lang/en/`，Blade 以 `__('檔名.鍵')` 讀取。不要把文案寫死在 view。                                                                                                                                          |
| **語言切換** | 每頁頁首右上角放切換按鈕，標籤以「目標語言的原文」顯示，也就是中文頁顯示 `English`，英文頁顯示 `繁體中文`。                                                                                                                                   |
| **圖片**     | 圖片內不放文字，替代文字兩種語言各自提供。                                                                                                                                                                                                    |
| **中文用語** | 遵守台灣正體中文用語，如「軟體」、「資料」、「品質」，避免「落地」、「颗粒度」等中國大陸用語。                                                                                                                                                |
| **標點**     | 中文使用全形標點與「」引號，避免括號補充說明。                                                                                                                                                                                                |
| **按鈕文字** | 用動詞開頭的短句，如「立即報名」、`Register now`。                                                                                                                                                                                            |
| **內部資訊** | 預算、席次限制、建議名額、內部估算等資訊不放上官網。                                                                                                                                                                                          |

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
| 1 | `/` 路由 | `/` 維持為官網 landing page，BBTI 測驗導引頁使用 `/bbti`。 |
| 2 | 色票 | 以取自 Logo 的官網色票為準，BBTI 之後沿用。 |
| 3 | 深色模式 | 官網支援 light 與 dark，預設跟隨系統設定，並提供手動切換按鈕。 |
| 4 | 8px 網格 | 官網只使用偶數步階，現有程式碼已調整。 |
| 5 | 小字對比 | 淺色模式的 `copper` 由 `#9a6f1e` 改為 `#7d5812`，小字標籤對比由 3.9:1 提升到 5.6:1。 |
| 6 | 贊助方案價格 | 中文頁 NT$ 20,000、10,000 與 5,000，英文頁 €500、250 與 125。席次限制不在官網顯示。 |
| 7 | 贊助層級辨識 | 以邊框顏色區分金、銀、銅獎級，並保留文字標題。贊助方案卡片與贊助商卡片共用同一組 `tier-*` 色票，視覺統一。 |
| 8 | 合作社群與贊助商 | 以 `config/` 管理清單，首頁顯示摘要，合作社群另有獨立頁面。 |
| 9 | 圓形 Logo | 合作社群與贊助商的 Logo 一律裁成圓形，由 `x-circle-logo` 處理，並以 `logo_fit` 指定填滿方式，避免色差。 |

### 待辦

| # | 項目 | 說明 | 建議 |
| :---- | :---- | :---- | :---- |
| 1 | **BBTI 導引頁尚未實作** | 路徑已定為 `/bbti`，文件已更新，但路由與 view 還沒有建立。 | 實作時沿用本文件的 token 與元件。 |
| 2 | **BBTI 色票對齊** | `docs/screen-design-spec.md` 的色票仍是舊值，如 `#F5F1E8` 與 `#2F6B4F`。 | 改成本文件第 3 節的 token，並重新驗證四個維度識別色在新的底色上的對比。 |
| 3 | **插畫風格並存** | 官網主視覺是水彩，BBTI 吉祥物是扁平插畫。 | 同一頁面不混用，水彩用於情境，吉祥物用於角色與測驗。 |
| 4 | **贊助商無聯絡方式** | 贊助方案區沒有行動按鈕。行為準則頁已有 `contact@mg.gatherconf.net`，但贊助洽詢尚未使用。 | 確認贊助洽詢是否使用同一個信箱，並加上次要按鈕。 |
| 5 | **水彩插畫解析度** | 為 1024 px 寬，桌機會被放大。 | 重新生成 1600 px 以上的版本。 |
| 6 | **dark 模式視覺檢查** | 對比已用數值驗證，但尚未實際在瀏覽器逐頁檢視。 | 在系統切換到深色模式後，檢查 Logo、吉祥物、水彩插畫與社群、贊助商卡片的呈現。 |
| 7 | **單一項目的版面** | 合作社群與贊助商目前各只有一筆，網格看起來偏空。 | 資料增加後再評估，必要時改為置中或 Logo 列。 |

---

## 12. 新增頁面時的檢查清單

1. 版面繼承 `layouts/app.blade.php`，並設定 `title` 與 `description`。
2. 所有文字放進 `lang/zh_TW/` 與 `lang/en/`，並建立對應的中英文路由，路由名稱為 `名稱` 與 `名稱.en`。頁面需宣告 `@section('route', '名稱')`，並把新頁面加進 `/sitemap.xml` 的清單。
3. 只使用第 3 節的 token，不新增色碼，也不寫死色碼。需要深色區塊時用 `bg-deep text-on-deep`，不要用 `bg-ink text-parchment`。
4. 區塊上下留白 `py-16`，內容寬度 `max-w-6xl` 或 `max-w-3xl`。
5. 每頁一個 H1，並且只有一個主要按鈕。
6. 圖片不含文字，且附上兩種語言的替代文字。
7. 間距只用偶數步階。
8. 先在手機寬度確認單欄版面，再確認 `md:` 以上的樣子，並且分別在 light 與 dark 檢查。
9. 新增 PHPUnit feature test，驗證中英文頁面皆回傳 200 並包含關鍵文字。
10. 清單型內容，如社群與贊助商，放在 `config/`，不寫死在 view。公司或社群名稱需提供兩種語言。

---

## 13. 內容維護

| 要做的事 | 怎麼做 |
| :---- | :---- |
| **修改文案** | 編輯 `lang/zh_TW/` 與 `lang/en/` 內對應的檔案。首頁在 `landing.php`，行為準則在 `conduct.php`，合作社群在 `communities.php`，贊助商區塊在 `sponsors.php`。 |
| **修改贊助方案價格** | 編輯兩個 `landing.php` 的 `tiers` 內的 `amount`。同時調整測試中的斷言。 |
| **新增合作社群** | 把 Logo 放進 `resources/images/community-logo/`，在 `config/communities.php` 加一筆 `name`，需有 `zh_TW` 與 `en`，以及 `logo`、`logo_fit`、可選的 `logo_padding` 與 `url`。首頁與合作社群頁會自動更新。 |
| **新增贊助商** | 把 Logo 放進 `resources/images/sponsor-logo/`，在 `config/sponsors.php` 加一筆，`name` 需提供 `zh_TW` 與 `en`，`tier` 為 `gold`、`silver` 或 `bronze`，`logo_fit` 為 `cover` 或 `contain`，需要時設定 `logo_padding`，選擇方式見 7.8。 |
| **更換圖片** | 直接覆蓋 `resources/images/` 內的同名檔案，若副檔名改變，需同步更新 view 的引用。 |
| **修改檢舉信箱** | 行為準則頁的信箱定義在 `resources/views/code-of-conduct.blade.php` 的 `$contactEmail`，只需改一處。 |
| **設定正式環境網域** | 正式環境的 `.env` 設定 `APP_URL=https://gatherconf.net`，細節見第 14 節。 |
| **更新分享預覽圖** | 覆蓋 `public/og-image.jpg`，尺寸固定 1200×630。社群平台會快取，更新後需到各平台的分享除錯工具重新抓取。 |
| **更新網站圖示** | 重新由 `resources/images/GatherConf-Logo.png` 產生 `public/` 內的 `favicon.ico`、`favicon-16x16.png`、`favicon-32x32.png`、`apple-touch-icon.png`、`icon-192.png`、`icon-512.png`。 |
| **修改報名連結** | 報名連結目前寫在 `landing/hero.blade.php` 與 `landing/footer.blade.php` 兩處。 |

改完需要重新 build 前端資源，執行 `npm run build`，或在開發時執行 `composer run dev`。提交前執行 `vendor/bin/pint --dirty --format agent` 與 `php artisan test --compact tests/Feature/ExampleTest.php`。

---

## 14. SEO 與分享資訊

所有頁面共用 `layouts/app.blade.php`，由它統一輸出下列資訊。頁面本身只需要提供 `route`、`title` 與 `description` 三個區段。

| 項目 | 規則 |
| :---- | :---- |
| **標題與描述** | 每頁各自撰寫，中英文版各一份。標題格式為「頁名 \| 共聚年會 GatherConf 2026」，描述一句話，約 80 字以內。 |
| **Canonical** | 指向目前語言版本的網址。 |
| **hreflang** | 每頁輸出 `zh-Hant-TW`、`en` 與 `x-default`，`x-default` 指向中文版。 |
| **Open Graph** | `og:type`、`og:site_name`、`og:title`、`og:description`、`og:url`、`og:locale` 與 `og:locale:alternate`、`og:image` 及其寬高與替代文字。 |
| **Twitter Card** | `summary_large_image`，標題、描述與圖片沿用 Open Graph。 |
| **分享預覽圖** | `public/og-image.jpg`，1200×630，取自年會宗旨的水彩插畫。圖內不放文字，所有語言共用。 |
| **網站圖示** | `favicon.ico` 內含 16、32、48 px，另有 PNG 版本與 `apple-touch-icon.png`，皆由官方 Logo 產生。Apple 圖示為不透明，放在米白底上。 |
| **Web App Manifest** | `public/site.webmanifest`，提供名稱、圖示與主題色。顯示模式為 `browser`，不是可安裝的 App。 |
| **主題色** | `theme-color` 依 light 與 dark 各設定一組，分別為 `#f4efe1` 與 `#121c15`。 |
| **結構化資料** | 只在首頁輸出 JSON-LD，包含 `Organization` 與 `Event`。`Event` 有時間、地點、主辦單位、語言與報名連結。時間固定為 2026-11-21 的 09:00 至 17:00，時區 +08:00。 |
| **robots.txt** | 由路由動態產生，禁止 `/admin`，並指向 sitemap。 |
| **sitemap.xml** | 由路由動態產生，列出所有頁面的中英文網址，並帶有 `hreflang` 互相參照。 |

注意事項：

- 網址以 `APP_URL` 為準。正式環境的網域是 `https://gatherconf.net`，`.env` 必須設定 `APP_URL=https://gatherconf.net`，否則 canonical、hreflang、Open Graph、JSON-LD 與 sitemap 都會輸出錯誤的位址。本機開發維持 `http://localhost:8000`。
- 設定 `APP_URL` 時使用 `https`、不加結尾斜線、不加 `www`，與 canonical 保持一致。若網域同時有 `www` 版本或 `http` 版本，需要在伺服器層導向 `https://gatherconf.net`，避免搜尋引擎視為重複內容。
- 正式環境部署後，檢查 `https://gatherconf.net/robots.txt` 與 `https://gatherconf.net/sitemap.xml`，確認內容中的網址都是 `https://gatherconf.net` 開頭。
- 正式環境的頁面網址對照：`https://gatherconf.net/`、`/en`、`/code-of-conduct`、`/en/code-of-conduct`、`/communities` 與 `/en/communities`。
- 活動時間、地點或報名連結異動時，除了畫面文字，也要同步更新 `landing/structured-data.blade.php`，否則搜尋結果的活動資訊會過時。
- 活動地址以台大官方地址為準，需要時請再確認。
- 目前沒有為非正式環境加上 `noindex`。若有公開的測試站，建議另外處理，避免被搜尋引擎收錄。
