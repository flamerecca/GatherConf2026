<?php

namespace App\Actions;

use App\Models\QuizAttempt;
use App\Models\ResultReferral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Follow a visitor from someone else's result page to finishing the quiz and signing up.
 * Only the first result page a browser opens counts, so one visitor is credited to one report.
 */
class TrackResultReferral
{
    private const VISITOR_COOKIE = 'gc_visitor';

    private const OWN_COOKIE = 'gc_own';

    private const MINUTES = 60 * 24 * 90;

    public function visit(Request $request, QuizAttempt $attempt): void
    {
        if ($this->isCrawler($request) || $this->isOwner($request, $attempt)) {
            return;
        }

        $token = $this->visitorToken($request);

        ResultReferral::query()->firstOrCreate(
            ['visitor_token' => $token],
            ['quiz_attempt_id' => $attempt->id],
        );
    }

    /**
     * Record the visitor's finished quiz and remember the report so it is never counted as a referral later.
     */
    public function completed(Request $request, QuizAttempt $attempt): void
    {
        $own = array_slice([...$this->ownAttempts($request), $attempt->uuid], -10);
        Cookie::queue(self::OWN_COOKIE, implode(',', $own), self::MINUTES);

        $this->pending($request, 'completed_at')?->update(['completed_at' => now()]);
    }

    public function registered(Request $request): void
    {
        $this->pending($request, 'registered_at')?->update(['registered_at' => now()]);
    }

    private function pending(Request $request, string $column): ?ResultReferral
    {
        $token = $request->cookie(self::VISITOR_COOKIE);

        if (! is_string($token) || ! Str::isUuid($token)) {
            return null;
        }

        return ResultReferral::query()->where('visitor_token', $token)->whereNull($column)->first();
    }

    private function visitorToken(Request $request): string
    {
        $token = $request->cookie(self::VISITOR_COOKIE);

        if (is_string($token) && Str::isUuid($token)) {
            return $token;
        }

        $token = (string) Str::uuid();
        Cookie::queue(self::VISITOR_COOKIE, $token, self::MINUTES);

        return $token;
    }

    private function isOwner(Request $request, QuizAttempt $attempt): bool
    {
        $memberId = $request->user('member')?->getKey();

        return ($memberId !== null && $memberId === $attempt->user_id) || in_array($attempt->uuid, $this->ownAttempts($request), true);
    }

    /**
     * @return list<string>
     */
    private function ownAttempts(Request $request): array
    {
        return array_values(array_filter(explode(',', (string) $request->cookie(self::OWN_COOKIE))));
    }

    private function isCrawler(Request $request): bool
    {
        return preg_match('/bot|crawl|spider|preview|facebookexternalhit|slurp|embedly|whatsapp|telegram/i', (string) $request->userAgent()) === 1;
    }
}
