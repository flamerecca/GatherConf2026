<?php

namespace Database\Seeders;

use App\Models\QuizQuestion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuizQuestionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ($this->questions() as $order => $question) {
            QuizQuestion::withTrashed()->firstOrCreate(['text' => $question['text']], [
                ...$question,
                'order' => $order + 1,
                'is_active' => true,
            ]);
        }
    }

    /**
     * 40 quiz questions, 10 per axis, five leaning toward each pole, grouped and ordered by axis:
     * SV (技術偏好), NM (探索心態), HA (工具哲學), GD (聚落動態).
     *
     * @return array<int, array<string, string>>
     */
    private function questions(): array
    {
        return [
            // SV — 技術偏好：S 系統 vs V 視覺
            ['axis' => 'SV', 'positive_pole' => 'S', 'text' => '比起畫面好不好看，我更在意程式底層架構寫得乾不乾淨。'],
            ['axis' => 'SV', 'positive_pole' => 'S', 'text' => '打開一個新專案，我通常先想清楚資料結構和系統設計，才動手。'],
            ['axis' => 'SV', 'positive_pole' => 'V', 'text' => '我會為了讓介面的動畫效果更順暢，花好幾個小時反覆調整。'],
            ['axis' => 'SV', 'positive_pole' => 'V', 'text' => '比起效能數字，我更容易被一個賞心悅目的畫面打動。'],
            ['axis' => 'SV', 'positive_pole' => 'S', 'text' => '遇到 bug，我習慣先追進 log 和呼叫堆疊，而不是先看畫面上哪裡怪怪的。'],
            ['axis' => 'SV', 'positive_pole' => 'S', 'text' => '需要在兩個方案之間取捨時，我會先比較它們的資料流、擴充性與維護成本。'],
            ['axis' => 'SV', 'positive_pole' => 'V', 'text' => '我常為了版面的留白、字體與配色，反覆調整到自己看了舒服為止。'],
            ['axis' => 'SV', 'positive_pole' => 'V', 'text' => '介紹作品時，我習慣先拿出截圖或操作畫面，而不是先講架構。'],
            ['axis' => 'SV', 'positive_pole' => 'S', 'text' => '我喜歡替系統畫出模組與依賴關係圖，把全貌看清楚才安心。'],
            ['axis' => 'SV', 'positive_pole' => 'V', 'text' => '同樣的功能，我會優先選擇操作起來手感更好、畫面更精緻的那一個。'],

            // NM — 探索心態：N 新奇開拓 vs M 穩固成熟
            ['axis' => 'NM', 'positive_pole' => 'N', 'text' => '看到剛發布的新框架或工具，我會忍不住想立刻拿來試試看。'],
            ['axis' => 'NM', 'positive_pole' => 'M', 'text' => '比起追新技術，我更傾向用已經驗證過、穩定可靠的方案。'],
            ['axis' => 'NM', 'positive_pole' => 'N', 'text' => '我喜歡在專案裡留一塊「實驗區」，嘗試還沒人用過的做法。'],
            ['axis' => 'NM', 'positive_pole' => 'M', 'text' => '面對還在測試階段的技術，我通常會選擇再觀望一陣子。'],
            ['axis' => 'NM', 'positive_pole' => 'N', 'text' => '重寫一套用習慣的舊系統，遠不如打造全新的東西讓我興奮。'],
            ['axis' => 'NM', 'positive_pole' => 'M', 'text' => '在正式環境上線的系統，我寧可多花時間測試，也不輕易換掉它的底層技術。'],
            ['axis' => 'NM', 'positive_pole' => 'N', 'text' => '比起把已知的事情做得更好，我更喜歡探索還沒人搞清楚的領域。'],
            ['axis' => 'NM', 'positive_pole' => 'M', 'text' => '我習慣讀完文件、看過別人的實戰經驗，才決定要不要採用一項技術。'],
            ['axis' => 'NM', 'positive_pole' => 'N', 'text' => '我常在週末動手做一個沒有明確用途、只是覺得有趣的小實驗。'],
            ['axis' => 'NM', 'positive_pole' => 'M', 'text' => '一套工具用久了越順手，我越不想為了新鮮感去換掉它。'],

            // HA — 工具哲學：H 親手介入 vs A 自動化生成
            ['axis' => 'HA', 'positive_pole' => 'A', 'text' => '遇到重複性的工作，我會優先花時間寫腳本或設定自動化來處理。'],
            ['axis' => 'HA', 'positive_pole' => 'H', 'text' => '重要的程式碼，我還是喜歡一行一行親手寫過，比較放心。'],
            ['axis' => 'HA', 'positive_pole' => 'A', 'text' => '我常用 AI／生成工具幫我先產出初稿，再從中挑選調整。'],
            ['axis' => 'HA', 'positive_pole' => 'H', 'text' => '比起交給工具生成，我更享受親手雕琢每一個細節的過程。'],
            ['axis' => 'HA', 'positive_pole' => 'A', 'text' => '只要能自動化的流程，我不會想留給人工重複執行。'],
            ['axis' => 'HA', 'positive_pole' => 'H', 'text' => '我認為理解每一行程式碼在做什麼，比快速完成任務更重要。'],
            ['axis' => 'HA', 'positive_pole' => 'A', 'text' => '能交給腳本或 AI 代勞的工作，我不會想自己動手做。'],
            ['axis' => 'HA', 'positive_pole' => 'H', 'text' => '就算有現成工具可用，我有時仍會自己從頭實作，只為了搞懂原理。'],
            ['axis' => 'HA', 'positive_pole' => 'A', 'text' => '我會花時間調整提示詞與工作流程，讓工具替我完成更多事情。'],
            ['axis' => 'HA', 'positive_pole' => 'H', 'text' => '產出的成果，我習慣自己逐項檢查與修改，才放心交出去。'],

            // GD — 聚落動態：G 熱情採集 vs D 深入探索
            ['axis' => 'GD', 'positive_pole' => 'G', 'text' => '做出一點小成果，我就迫不及待想拿去聚落裡跟大家分享討論。'],
            ['axis' => 'GD', 'positive_pole' => 'D', 'text' => '比起在社群裡熱鬧交流，我更喜歡一個人專注鑽研到底。'],
            ['axis' => 'GD', 'positive_pole' => 'G', 'text' => '我喜歡逛遍各種社群、論壇，收集大家正在做什麼的最新消息。'],
            ['axis' => 'GD', 'positive_pole' => 'D', 'text' => '一旦投入某個問題，我可以連續好幾天不太理會外界動態，直到搞懂為止。'],
            ['axis' => 'GD', 'positive_pole' => 'G', 'text' => '參加聚落的活動或聚會，對我來說是很重要的能量來源。'],
            ['axis' => 'GD', 'positive_pole' => 'D', 'text' => '我喜歡把一個主題研究得很透徹，而不是同時涉獵很多領域。'],
            ['axis' => 'GD', 'positive_pole' => 'G', 'text' => '遇到卡關的問題，我第一個念頭是到社群裡問問大家的想法。'],
            ['axis' => 'GD', 'positive_pole' => 'D', 'text' => '比起即時討論，我更喜歡一個人安靜地整理思緒，再把成果寫下來。'],
            ['axis' => 'GD', 'positive_pole' => 'G', 'text' => '我很常把好用的工具或文章轉貼給同伴，也樂於聽聽大家的回饋。'],
            ['axis' => 'GD', 'positive_pole' => 'D', 'text' => '投入一個主題時，我不太需要外界的討論或鼓勵，自己就能持續推進。'],
        ];
    }
}
