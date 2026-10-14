@extends('member.layout')

@php
    $zhUrl = route('member.messages.show', ['friendship' => $friendship]);
    $enUrl = route('member.messages.show.en', ['friendship' => $friendship]);
    $s = app()->getLocale() === 'en' ? '.en' : '';
@endphp

@section('route', 'member.friends')
@section('zh_url', $zhUrl)
@section('en_url', $enUrl)
@section('robots', 'noindex')
@section('title', __('member.messages.title', ['name' => $friend->name]))
@section('description', __('member.messages.title', ['name' => $friend->name]))
@section('heading', __('member.messages.heading', ['name' => $friend->name]))

@section('member_body')
    <p class="mt-4 text-xs text-ink/70">{{ __('member.messages.privacy') }}</p>

    @if ($olderBeforeId)
        <a href="{{ route('member.messages.show'.$s, ['friendship' => $friendship]) }}?before={{ $olderBeforeId }}"
           class="mt-6 inline-block text-sm font-medium text-moss underline underline-offset-4">
            {{ __('member.messages.older') }}
        </a>
    @endif

    <ol class="mt-6 space-y-3">
        @forelse ($messages as $item)
            @php($mine = $item->sender_id === $me->getKey())
            <li class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[85%] rounded-2xl border border-ink/15 p-3 {{ $mine ? 'bg-moss/15' : 'bg-parchment-dark' }}">
                    <p class="text-xs font-semibold text-ink/70">{{ $mine ? __('member.messages.me') : $friend->name }}</p>
                    <p class="mt-1 whitespace-pre-line break-words">{{ $item->body }}</p>
                    <p class="mt-1 text-xs text-ink/60">
                        {{ $item->created_at->format('Y-m-d H:i') }}
                        @if ($mine && $item->read_at)
                            · {{ __('member.messages.read') }}
                        @endif
                    </p>
                </div>
            </li>
        @empty
            <li class="text-sm text-ink/70">{{ __('member.messages.empty') }}</li>
        @endforelse
    </ol>

    <form method="POST" action="{{ route('member.messages.store'.$s, ['friendship' => $friendship]) }}"
          class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-4">
        @csrf
        <label for="body" class="sr-only">{{ __('member.messages.send') }}</label>
        <textarea id="body" name="body" rows="3" maxlength="{{ \App\Models\Message::MAX_LENGTH }}" required
                  placeholder="{{ __('member.messages.placeholder') }}"
                  class="w-full rounded-xl border border-ink/30 bg-parchment px-4 py-3 focus:border-moss focus:outline-none focus:ring-2 focus:ring-moss/40">{{ old('body') }}</textarea>
        @error('body')
            <p class="text-sm text-copper" role="alert">{{ $message }}</p>
        @enderror
        <x-member-submit>{{ __('member.messages.send') }}</x-member-submit>
    </form>

    <a href="{{ route('member.friends'.$s) }}" class="mt-6 inline-block text-sm font-medium text-moss underline underline-offset-4">
        {{ __('member.messages.back') }}
    </a>
@endsection
