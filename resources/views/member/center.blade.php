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
        <form method="POST" action="{{ route('member.logout'.(app()->getLocale() === 'en' ? '.en' : '')) }}">
            @csrf
            <x-member-submit>{{ __('member.center.logout') }}</x-member-submit>
        </form>
    </div>
@endsection
