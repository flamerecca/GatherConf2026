@extends('member.layout')

@section('route', 'member.register')
@section('robots', 'noindex')
@section('title', __('member.register.title'))
@section('description', __('member.register.description'))
@section('heading', __('member.register.heading'))

@section('member_body')
    <form method="POST" action="{{ route('member.register.store'.(app()->getLocale() === 'en' ? '.en' : '')) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <x-member-field name="name" :label="__('member.fields.name')" autocomplete="name" required />
        <x-member-field name="email" type="email" :label="__('member.fields.email')" autocomplete="email" required />
        <x-member-field name="organization" :label="__('member.fields.organization')" autocomplete="organization" />
        <p class="text-sm text-ink/70">{{ __('member.register.no_password_note') }}</p>
        <x-member-submit>{{ __('member.register.submit') }}</x-member-submit>
    </form>
    <p class="mt-6 text-sm">
        {{ __('member.register.have_account') }}
        <a href="{{ route('member.login'.(app()->getLocale() === 'en' ? '.en' : '')) }}" class="font-semibold text-moss underline underline-offset-4">{{ __('member.register.login_link') }}</a>
    </p>
@endsection
