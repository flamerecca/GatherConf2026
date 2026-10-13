@extends('member.layout')

@section('route', 'member.verification.notice')
@section('robots', 'noindex')
@section('title', __('member.verify.title'))
@section('description', __('member.verify.title'))
@section('heading', __('member.verify.heading'))
@section('intro', __('member.verify.body'))

@section('member_body')
    <form method="POST" action="{{ route('member.verification.send'.(app()->getLocale() === 'en' ? '.en' : '')) }}" class="mt-8">
        @csrf
        <x-member-submit>{{ __('member.verify.resend') }}</x-member-submit>
    </form>
@endsection
