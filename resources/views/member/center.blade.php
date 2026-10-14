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
        <form method="POST" action="{{ route('member.logout'.(app()->getLocale() === 'en' ? '.en' : '')) }}">
            @csrf
            <x-member-submit>{{ __('member.center.logout') }}</x-member-submit>
        </form>
    </div>
@endsection
