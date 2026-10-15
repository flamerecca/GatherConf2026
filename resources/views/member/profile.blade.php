@extends('member.layout')

@section('route', 'member.profile.edit')
@section('robots', 'noindex')
@section('title', __('member.profile.title'))
@section('description', __('member.profile.title'))
@section('heading', __('member.profile.heading'))
@section('intro', __('member.profile.intro'))

@section('member_body')
    <form method="POST" action="{{ route('member.profile.update'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        @method('PUT')
        <x-member-field name="name" :label="__('member.profile.nickname')" :value="$member->name" autocomplete="nickname" required />
        <x-member-submit>{{ __('member.profile.submit') }}</x-member-submit>
    </form>
@endsection
