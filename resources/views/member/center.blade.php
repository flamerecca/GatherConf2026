@extends('member.layout')

@section('route', 'member.center')
@section('robots', 'noindex')
@section('title', __('member.center.title'))
@section('description', __('member.center.title'))
@section('heading', __('member.center.heading'))

@section('member_body')
    <div class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        <p class="text-lg font-semibold">{{ __('member.center.welcome', ['name' => $member->name]) }}</p>
        <dl class="space-y-2 text-sm">
            <div>
                <dt class="font-semibold">{{ __('member.fields.email') }}</dt>
                <dd class="text-ink/80">{{ $member->email }}</dd>
            </div>
            <div>
                <dt class="font-semibold">{{ __('member.center.organization') }}</dt>
                <dd class="text-ink/80">{{ $member->organization ?? __('member.center.not_set') }}</dd>
            </div>
        </dl>
        <a href="{{ route('member.profile.edit'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
           class="block text-sm font-medium text-moss underline underline-offset-4">{{ __('member.center.edit_profile') }}</a>
        @unless ($hasPassword)
            <p data-set-password-prompt class="rounded-2xl border border-copper p-4 text-sm">
                {{ __('member.center.set_password_prompt') }}
                <a href="{{ route('member.password.edit'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
                   class="font-semibold text-moss underline underline-offset-4">{{ __('member.center.set_password') }}</a>
            </p>
        @else
            <a href="{{ route('member.password.edit'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
               class="block text-sm font-medium text-moss underline underline-offset-4">{{ __('member.center.change_password') }}</a>
        @endunless
        <a href="{{ route('member.friends'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
           data-pending-invitations="{{ $pendingInvitations }}"
           class="inline-flex items-center gap-2 font-semibold text-moss underline underline-offset-4">
            {{ __('member.center.friends') }}
            @if ($pendingInvitations > 0)
                <span class="rounded-full bg-moss px-2 py-0.5 text-xs text-on-accent">{{ $pendingInvitations }}</span>
            @endif
        </a>
        <p data-unread-messages="{{ $unreadMessages }}" class="text-sm text-ink/80">
            {{ __('member.center.unread_messages') }}：{{ $unreadMessages }}
        </p>
        <a href="{{ route('member.tasks'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
           data-carrots="{{ $member->carrots }}"
           @class(['block underline underline-offset-4', 'font-semibold text-moss' => $member->carrots >= 0, 'text-red-700 dark:text-red-400 font-bold' => $member->carrots < 0])>
            🥕 {{ __('carrots.balance', ['count' => $member->carrots]) }} · {{ __('carrots.list.heading') }}
        </a>
        @php($quizSuffix = app()->getLocale() === 'en' ? '.en' : '')
        @if ($lastAttempt?->personalityType)
            <div data-last-bbti="{{ $lastAttempt->type_code }}" class="rounded-2xl border border-ink/15 p-4 text-sm">
                <p class="font-semibold">{{ __('quiz.last.heading') }}</p>
                <div class="mt-2 flex items-center gap-4">
                    @if ($bunny = $lastAttempt->personalityType->bunnyImagePath())
                        @php($thumbnails = $lastAttempt->personalityType->bunnyThumbnailPaths())
                        <img src="{{ Vite::asset($bunny) }}"
                             @if ($thumbnails) srcset="{{ collect($thumbnails)->map(fn (string $file, int $width) => Vite::asset($file).' '.$width.'w')->implode(', ') }}" sizes="80px" @endif
                             width="80" height="80" loading="lazy" decoding="async" data-bunny-thumbnail="{{ $lastAttempt->type_code }}"
                             alt="{{ __('quiz.result.bunny_alt', ['code' => $lastAttempt->type_code, 'name' => $lastAttempt->personalityType->name]) }}"
                             class="size-20 shrink-0 rounded-2xl border-2 border-moss/60 bg-white object-cover">
                    @endif
                    <div>
                        <p class="text-lg font-bold">{{ $lastAttempt->type_code }} <span class="text-moss">{{ $lastAttempt->personalityType->name }}</span></p>
                        <p class="text-ink/70">{{ __('quiz.last.completed_at', ['date' => $lastAttempt->completed_at->format('Y-m-d')]) }}</p>
                        <a href="{{ route('quiz.result'.$quizSuffix, $lastAttempt) }}" class="mt-2 inline-block font-semibold text-moss underline underline-offset-4">{{ __('quiz.last.view') }}</a>
                        <span aria-hidden="true"> · </span>
                        <a href="{{ route('quiz'.$quizSuffix) }}" class="font-semibold text-moss underline underline-offset-4">{{ __('quiz.last.retake') }}</a>
                    </div>
                </div>
            </div>
        @else
            <a href="{{ route('bbti'.$quizSuffix) }}" data-take-bbti class="block font-semibold text-moss underline underline-offset-4">{{ __('quiz.last.take') }}</a>
        @endif
        <a href="{{ route('member.bottles'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
           data-bottle-holding="{{ \App\Models\DriftBottle::heldCount($member) }}"
           class="block font-semibold text-moss underline underline-offset-4">
            🍾 {{ __('bottles.index.heading') }}
        </a>
        <a href="{{ route('member.community-messages'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
           class="block font-semibold text-moss underline underline-offset-4">
            {{ __('community-messages.list.heading') }}
        </a>
        <form method="POST" action="{{ route('member.logout'.(app()->getLocale() === 'en' ? '.en' : '')) }}">
            @csrf
            <x-member-submit>{{ __('member.center.logout') }}</x-member-submit>
        </form>
    </div>
@endsection
