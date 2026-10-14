@extends('member.layout')

@php
    $zhUrl = route('member.friends.add', ['code' => $code]);
    $enUrl = route('member.friends.add.en', ['code' => $code]);
    $s = app()->getLocale() === 'en' ? '.en' : '';
@endphp

@section('route', 'member.friends')
@section('zh_url', $zhUrl)
@section('en_url', $enUrl)
@section('robots', 'noindex')
@section('title', __('member.friends.add.title'))
@section('description', __('member.friends.add.title'))
@section('heading', __('member.friends.add.heading'))

@section('member_body')
    <div class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        <p class="text-xl font-bold">{{ $owner->name }}</p>
        @if ($owner->organization)
            <p class="text-sm text-ink/80">{{ __('member.friends.add.organization') }}：{{ $owner->organization }}</p>
        @endif

        @if ($isSelf)
            <p>{{ __('member.friends.add.self') }}</p>
        @elseif ($relation === 'friends')
            <p>{{ __('member.friends.add.already_friends') }}</p>
        @elseif ($relation === 'sent')
            <p>{{ __('member.friends.add.already_invited') }}</p>
        @else
            <p>{{ $relation === 'received' ? __('member.friends.add.invitation_received') : __('member.friends.add.intro', ['name' => $owner->name]) }}</p>
            <form method="POST" action="{{ route('member.friends.invite'.$s, ['code' => $code]) }}">
                @csrf
                <x-member-submit>
                    {{ $relation === 'received' ? __('member.friends.add.accept_submit') : __('member.friends.add.submit') }}
                </x-member-submit>
            </form>
        @endif

        <a href="{{ route('member.friends'.$s) }}" class="inline-block text-sm font-medium text-moss underline underline-offset-4">
            {{ __('member.friends.add.back_to_friends') }}
        </a>
    </div>
@endsection
