@extends('member.layout')

@section('route', 'member.bottles')
@section('robots', 'noindex')
@section('title', __('bottles.show.title'))
@section('description', __('bottles.show.title'))
@section('heading', __('bottles.show.heading'))

@section('member_body')
    @php($s = app()->getLocale() === 'en' ? '.en' : '')

    <div class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6" data-bottle-status="{{ $bottle->status() }}">
        @if ($isRecipient)
            <p class="text-sm font-semibold text-moss">{{ __('bottles.show.received_label') }}</p>
            <p class="whitespace-pre-line leading-relaxed">{{ $bottle->body }}</p>

            @if ($bottle->replied_at)
                <div class="rounded-2xl border border-ink/15 p-4">
                    <p class="text-sm font-semibold">{{ __('bottles.show.your_reply') }}</p>
                    <p class="mt-2 whitespace-pre-line">{{ $bottle->reply_body }}</p>
                </div>
            @else
                <form method="POST" action="{{ route('member.bottles.reply'.$s, ['bottle' => $bottle]) }}" class="space-y-3">
                    @csrf
                    <label for="reply-body" class="block text-sm font-semibold">{{ __('bottles.show.reply_heading') }}</label>
                    <textarea id="reply-body" name="body" rows="4" maxlength="300" required
                              class="w-full rounded-xl border border-ink/60 bg-parchment px-4 py-3 focus:border-moss">{{ old('body') }}</textarea>
                    @error('body')
                        <p class="text-sm text-copper" role="alert">{{ $message }}</p>
                    @enderror
                    <x-member-submit>{{ __('bottles.show.reply_submit') }}</x-member-submit>
                </form>
            @endif

            <form method="POST" action="{{ route('member.bottles.report'.$s, ['bottle' => $bottle]) }}" class="space-y-3 border-t border-ink/15 pt-4">
                @csrf
                <input type="hidden" name="target" value="bottle">
                <label for="report-reason" class="block text-sm">{{ __('bottles.show.reason') }}</label>
                <input id="report-reason" name="reason" type="text" maxlength="200"
                       class="w-full rounded-xl border border-ink/60 bg-parchment px-4 py-2">
                <button type="submit" class="text-sm font-medium text-copper underline underline-offset-4">{{ __('bottles.show.report') }}</button>
            </form>
        @else
            <p class="text-sm font-semibold text-moss">{{ __('bottles.show.sent_label') }}</p>
            @if ($bottle->removed_at)
                <p class="text-copper">{{ __('bottles.show.removed') }}</p>
            @else
                <p class="whitespace-pre-line leading-relaxed">{{ $bottle->body }}</p>

                @if ($bottle->replied_at && $bottle->reply_removed_at)
                    <p class="text-copper">{{ __('bottles.show.reply_removed') }}</p>
                @elseif ($bottle->replied_at)
                    <div class="rounded-2xl border border-ink/15 p-4">
                        <p class="text-sm font-semibold">{{ __('bottles.show.their_reply') }}</p>
                        <p class="mt-2 whitespace-pre-line">{{ $bottle->reply_body }}</p>
                    </div>
                    <form method="POST" action="{{ route('member.bottles.report'.$s, ['bottle' => $bottle]) }}" class="space-y-3 border-t border-ink/15 pt-4">
                        @csrf
                        <input type="hidden" name="target" value="reply">
                        <label for="report-reason" class="block text-sm">{{ __('bottles.show.reason') }}</label>
                        <input id="report-reason" name="reason" type="text" maxlength="200"
                               class="w-full rounded-xl border border-ink/60 bg-parchment px-4 py-2">
                        <button type="submit" class="text-sm font-medium text-copper underline underline-offset-4">{{ __('bottles.show.report_reply') }}</button>
                    </form>
                @else
                    <p class="text-sm text-ink/70">{{ __('bottles.show.waiting') }}</p>
                @endif
            @endif
        @endif
    </div>

    <a href="{{ route('member.bottles'.$s) }}" class="mt-6 inline-block text-sm font-medium text-moss underline underline-offset-4">
        {{ __('bottles.show.back') }}
    </a>
@endsection
