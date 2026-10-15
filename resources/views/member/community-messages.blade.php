@extends('member.layout')

@section('route', 'member.community-messages')
@section('robots', 'noindex')
@section('title', __('community-messages.list.title'))
@section('description', __('community-messages.list.title'))
@section('heading', __('community-messages.list.heading'))

@section('member_body')
    @php($s = app()->getLocale() === 'en' ? '.en' : '')

    @forelse ($messages as $message)
        <div data-community-message-status="{{ $message->status }}"
             class="mt-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
            <p class="text-sm text-ink/70">{{ $message->community_name }} · {{ $message->created_at->format('Y-m-d H:i') }}</p>
            <p class="mt-1 font-semibold">{{ $message->subject }}</p>
            <p class="mt-2 text-sm font-medium text-moss">{{ __('community-messages.status.'.$message->status) }}</p>
        </div>
    @empty
        <p class="mt-8 text-sm text-ink/70">{{ __('community-messages.list.empty') }}</p>
    @endforelse

    <a href="{{ route('communities'.$s) }}" class="mt-8 inline-block text-sm font-medium text-moss underline underline-offset-4">
        {{ __('community-messages.list.to_communities') }}
    </a>
@endsection
