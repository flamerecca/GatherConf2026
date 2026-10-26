<?php

namespace App\Actions;

use App\Models\QuizAttempt;
use Illuminate\Support\Facades\Storage;

class PurgeGuestQuizAttempts
{
    /**
     * Permanently delete the reports that no member owns, together with their unique bunny images.
     *
     * @return int Number of deleted reports.
     */
    public function __invoke(): int
    {
        $disk = Storage::disk(config('filesystems.unique_bunny_disk'));
        $deleted = 0;

        QuizAttempt::withTrashed()->whereNull('user_id')->each(function (QuizAttempt $attempt) use ($disk, &$deleted): void {
            if ($attempt->unique_bunny_path !== null) {
                $disk->delete($attempt->unique_bunny_path);
            }

            $attempt->forceDelete();
            $deleted++;
        });

        return $deleted;
    }
}
