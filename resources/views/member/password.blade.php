@extends('member.layout')

@section('route', 'member.password.edit')
@section('robots', 'noindex')
@section('title', $hasPassword ? __('member.password.change_title') : __('member.password.set_title'))
@section('description', __('member.password.set_title'))
@section('heading', $hasPassword ? __('member.password.change_heading') : __('member.password.set_heading'))
@if (! $hasPassword)
    @section('intro', __('member.password.set_intro'))
@endif

@section('member_body')
    <form method="POST" action="{{ route('member.password.change'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        @method('PUT')
        @if ($needsCurrent)
            <x-member-field name="current_password" type="password" :label="__('member.password.current')" autocomplete="current-password" required />
        @endif
        <x-member-field name="password" type="password" :label="__('member.fields.password')" autocomplete="new-password" required />
        <x-member-field name="password_confirmation" type="password" :label="__('member.fields.password_confirmation')" autocomplete="new-password" required />
        <x-member-submit>{{ __('member.password.submit') }}</x-member-submit>
    </form>
@endsection
