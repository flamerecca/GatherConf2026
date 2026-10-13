@extends('member.layout')

@section('route', 'member.password.request')
@section('robots', 'noindex')
@section('title', __('member.forgot.title'))
@section('description', __('member.forgot.title'))
@section('heading', __('member.forgot.heading'))
@section('intro', __('member.forgot.intro'))

@section('member_body')
    <form method="POST" action="{{ route('member.password.email'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <x-member-field name="email" type="email" :label="__('member.fields.email')" autocomplete="email" required />
        <x-member-submit>{{ __('member.forgot.submit') }}</x-member-submit>
    </form>
@endsection
