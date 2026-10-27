<?php

namespace App\Http\Controllers\Member;

use App\Actions\TrackResultReferral;
use App\Models\Member;
use App\Models\QuizAttempt;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleLoginController extends MemberController
{
    private const SESSION_KEY = 'member_google_login';

    public function redirect(Request $request): SymfonyRedirectResponse
    {
        abort_unless(filled(config('services.google.client_id')), 404);

        $attempt = $request->query('attempt');

        $request->session()->put(self::SESSION_KEY, [
            'locale' => app()->getLocale(),
            'attempt' => is_string($attempt) && Str::isUuid($attempt) ? $attempt : null,
        ]);

        return Socialite::driver('google')->redirectUrl(route('member.google.callback'))->redirect();
    }

    public function callback(Request $request, TrackResultReferral $referral): RedirectResponse
    {
        $context = $request->session()->pull(self::SESSION_KEY, []);

        app()->setLocale($context['locale'] ?? 'zh_TW');

        try {
            $google = Socialite::driver('google')->redirectUrl(route('member.google.callback'))->user();
        } catch (Throwable) {
            return $this->failed('member.errors.google_failed');
        }

        $email = Str::lower(trim((string) $google->getEmail()));

        if ($email === '' || ($google->user['email_verified'] ?? false) !== true) {
            return $this->failed('member.errors.google_failed');
        }

        $member = Member::query()->where('email', $email)->first();

        if ($member !== null && ! $member->is_active) {
            return $this->failed('member.errors.inactive');
        }

        if ($member === null) {
            $member = Member::query()->create([
                'name' => Str::limit(trim((string) $google->getName()) ?: Str::before($email, '@'), 255, ''),
                'email' => $email,
                'is_active' => true,
            ]);

            $referral->registered($request);

            if (filled($context['attempt'] ?? null)) {
                QuizAttempt::query()->where('uuid', $context['attempt'])->whereNull('user_id')->first()?->update(['user_id' => $member->id]);
            }
        }

        if (! $member->hasVerifiedEmail()) {
            $member->discardCredentials();
            $member->markEmailAsVerified();
            event(new Verified($member));
        }

        $this->guard()->login($member);
        $request->session()->regenerate();
        $request->session()->put('member_link_login', ['member_id' => $member->getKey(), 'at' => now()->timestamp]);

        return redirect()->intended($this->localizedRoute('member.center'));
    }

    private function failed(string $messageKey): RedirectResponse
    {
        return redirect($this->localizedRoute('member.login'))->withErrors(['google' => __($messageKey)]);
    }
}
