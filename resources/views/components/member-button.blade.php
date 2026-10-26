@php($isEn = app()->getLocale() === 'en')
@auth('member')
    @php($carrots = auth('member')->user()->carrots)
    <a href="{{ $isEn ? route('member.center.en') : route('member.center') }}"
       @class([
           'rounded-full px-4 py-2 text-sm transition',
           'bg-moss font-medium text-on-accent hover:bg-ink' => $carrots >= 0,
           'bg-red-700 font-bold text-white hover:bg-red-900 dark:bg-red-600 dark:hover:bg-red-800' => $carrots < 0,
       ])>
        {{ __('landing.member_center_carrots', ['count' => $carrots]) }}
    </a>
@else
    <a href="{{ $isEn ? route('member.login.en') : route('member.login') }}"
       class="rounded-full bg-moss px-4 py-2 text-sm font-medium text-on-accent transition hover:bg-ink">
        {{ __('landing.member_login_link') }}
    </a>
@endauth
