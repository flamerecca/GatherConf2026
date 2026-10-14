@extends('member.layout')

@section('route', 'member.register')
@section('robots', 'noindex')
@section('title', __('member.register.sent_title'))
@section('description', __('member.register.sent_title'))
@section('heading', __('member.register.sent_heading'))
@section('intro', __('member.register.sent_body'))

@section('member_body')
    <p class="mt-8 text-sm">
        <a href="{{ route('member.login'.(app()->getLocale() === 'en' ? '.en' : '')) }}" class="font-semibold text-moss underline underline-offset-4">{{ __('member.register.login_link') }}</a>
    </p>
@endsection
