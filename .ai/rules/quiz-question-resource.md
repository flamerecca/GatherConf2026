---
paths:
  - 'app/Filament/Resources/QuizQuestionResource/**'
---

# Quiz Question Resource

## positive_pole 選項須依 axis 動態限制
quiz_questions.positive_pole 在資料庫是涵蓋全部 8 個字母的 enum，沒有跟 axis 綁定合法組合。實作 QuizQuestionResource 表單時，positive_pole 的 Select 選項必須用 Get $get 依 axis 動態限制為對應的兩個值（SV→S/V, NM→N/M, HA→H/A, GD→G/D），避免內容人員填出不合法組合（例如 axis=SV 卻選 positive_pole=H）。這類錯誤不會報錯，只會讓計分邏輯算錯。對照表與細節見 docs/mbti-quiz-planning.md、docs/filament-admin-guide.md 第 5 節。

> 2026-08-22 更新：軸代碼與極性字母已從舊版 EI/SN/TF/JP 改為 SV/NM/HA/GD，上表已同步。
