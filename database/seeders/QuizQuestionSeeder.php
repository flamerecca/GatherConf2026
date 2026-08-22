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
            QuizQuestion::create([
                ...$question,
                'order' => $order + 1,
                'is_active' => true,
            ]);
        }
    }

    /**
     * 20 quiz questions, 5 per axis, grouped and ordered by axis:
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

            // NM — 探索心態：N 新奇開拓 vs M 穩固成熟
            ['axis' => 'NM', 'positive_pole' => 'N', 'text' => '看到剛發布的新框架或工具，我會忍不住想立刻拿來試試看。'],
            ['axis' => 'NM', 'positive_pole' => 'M', 'text' => '比起追新技術，我更傾向用已經驗證過、穩定可靠的方案。'],
            ['axis' => 'NM', 'positive_pole' => 'N', 'text' => '我喜歡在專案裡留一塊「實驗區」，嘗試還沒人用過的做法。'],
            ['axis' => 'NM', 'positive_pole' => 'M', 'text' => '面對還在測試階段的技術，我通常會選擇再觀望一陣子。'],
            ['axis' => 'NM', 'positive_pole' => 'N', 'text' => '重寫一套用習慣的舊系統，遠不如打造全新的東西讓我興奮。'],

            // HA — 工具哲學：H 親手介入 vs A 自動化生成
            ['axis' => 'HA', 'positive_pole' => 'A', 'text' => '遇到重複性的工作，我會優先花時間寫腳本或設定自動化來處理。'],
            ['axis' => 'HA', 'positive_pole' => 'H', 'text' => '重要的程式碼，我還是喜歡一行一行親手寫過，比較放心。'],
            ['axis' => 'HA', 'positive_pole' => 'A', 'text' => '我常用 AI／生成工具幫我先產出初稿，再從中挑選調整。'],
            ['axis' => 'HA', 'positive_pole' => 'H', 'text' => '比起交給工具生成，我更享受親手雕琢每一個細節的過程。'],
            ['axis' => 'HA', 'positive_pole' => 'A', 'text' => '只要能自動化的流程，我不會想留給人工重複執行。'],

            // GD — 聚落動態：G 熱情採集 vs D 深入探索
            ['axis' => 'GD', 'positive_pole' => 'G', 'text' => '做出一點小成果，我就迫不及待想拿去聚落裡跟大家分享討論。'],
            ['axis' => 'GD', 'positive_pole' => 'D', 'text' => '比起在社群裡熱鬧交流，我更喜歡一個人專注鑽研到底。'],
            ['axis' => 'GD', 'positive_pole' => 'G', 'text' => '我喜歡逛遍各種社群、論壇，收集大家正在做什麼的最新消息。'],
            ['axis' => 'GD', 'positive_pole' => 'D', 'text' => '一旦投入某個問題，我可以連續好幾天不太理會外界動態，直到搞懂為止。'],
            ['axis' => 'GD', 'positive_pole' => 'G', 'text' => '參加聚落的活動或聚會，對我來說是很重要的能量來源。'],
        ];
    }
}
