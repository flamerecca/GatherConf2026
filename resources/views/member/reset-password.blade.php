@extends('member.layout')

@php
    $zhResetUrl = route('member.password.reset', ['token' => $token, 'email' => $email]);
    $enResetUrl = route('member.password.reset.en', ['token' => $token, 'email' => $email]);
@endphp

@section('route', 'member.password.request')
@section('zh_url', $zhResetUrl)
@section('en_url', $enResetUrl)
@section('robots', 'noindex')
@section('title', __('member.reset.title'))
@section('description', __('member.reset.title'))
@section('heading', __('member.reset.heading'))

@section('member_body')
    <form method="POST" action="{{ route('member.password.update'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-member-field name="email" type="email" :label="__('member.fields.email')" :value="$email" autocomplete="email" required />
        <x-member-field name="password" type="password" :label="__('member.fields.password')" autocomplete="new-password" required />
        <x-member-field name="password_confirmation" type="password" :label="__('member.fields.password_confirmation')" autocomplete="new-password" required />
        <x-member-submit>{{ __('member.reset.submit') }}</x-member-submit>
    </form>
@endsection
