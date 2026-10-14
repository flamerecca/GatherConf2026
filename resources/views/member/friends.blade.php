@extends('member.layout')

@section('route', 'member.friends')
@section('robots', 'noindex')
@section('title', __('member.friends.title'))
@section('description', __('member.friends.title'))
@section('heading', __('member.friends.heading'))

@section('member_body')
    @php($s = app()->getLocale() === 'en' ? '.en' : '')

    <section class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        <h2 class="text-xl font-bold text-moss">{{ __('member.friends.my_code_heading') }}</h2>
        <p class="text-sm leading-relaxed text-ink/80">{{ __('member.friends.my_code_intro') }}</p>
        <x-qr-code :value="$friendUrl" :label="__('member.friends.qr_alt')" class="mx-auto" />
        <input type="text" readonly value="{{ $friendUrl }}" aria-label="{{ __('member.friends.my_code_heading') }}"
               class="w-full rounded-xl border border-ink/30 bg-parchment px-4 py-3 text-sm"
               onclick="this.select()">
        <form method="POST" action="{{ route('member.friends.code.regenerate'.$s) }}">
            @csrf
            <p class="mb-3 text-xs text-ink/70">{{ __('member.friends.regenerate_note') }}</p>
            <x-member-submit>{{ __('member.friends.regenerate') }}</x-member-submit>
        </form>
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('member.friends.received_heading') }}</h2>
        @forelse ($received as $friendship)
            @php($other = $friendship->otherMember($member))
            <div class="mt-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ $other->name }}</p>
                @if ($other->organization)
                    <p class="text-sm text-ink/70">{{ $other->organization }}</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('member.friends.accept'.$s, ['friendship' => $friendship]) }}">
                        @csrf
                        <x-member-submit>{{ __('member.friends.accept') }}</x-member-submit>
                    </form>
                    <form method="POST" action="{{ route('member.friends.decline'.$s, ['friendship' => $friendship]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-full border border-ink/30 px-6 py-3 font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.decline') }}</button>
                    </form>
                    <form method="POST" action="{{ route('member.friends.block'.$s, ['member' => $other]) }}">
                        @csrf
                        <button type="submit" class="rounded-full border border-ink/30 px-6 py-3 font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.block') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('member.friends.empty_received') }}</p>
        @endforelse
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('member.friends.friends_heading') }}</h2>
        @forelse ($friends as $friendship)
            @php($other = $friendship->otherMember($member))
            <div class="mt-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ $other->name }}</p>
                @if ($other->organization)
                    <p class="text-sm text-ink/70">{{ $other->organization }}</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('member.friends.decline'.$s, ['friendship' => $friendship]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-full border border-ink/30 px-6 py-3 font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.remove') }}</button>
                    </form>
                    <form method="POST" action="{{ route('member.friends.block'.$s, ['member' => $other]) }}">
                        @csrf
                        <button type="submit" class="rounded-full border border-ink/30 px-6 py-3 font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.block') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('member.friends.empty_friends') }}</p>
        @endforelse
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('member.friends.sent_heading') }}</h2>
        @forelse ($sent as $friendship)
            @php($other = $friendship->otherMember($member))
            <div class="mt-4 flex items-center justify-between gap-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ $other->name }}</p>
                <form method="POST" action="{{ route('member.friends.decline'.$s, ['friendship' => $friendship]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-full border border-ink/30 px-4 py-2 text-sm font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.cancel') }}</button>
                </form>
            </div>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('member.friends.empty_sent') }}</p>
        @endforelse
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('member.friends.blocked_heading') }}</h2>
        @forelse ($blocked as $block)
            <div class="mt-4 flex items-center justify-between gap-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ $block->blocked->name }}</p>
                <form method="POST" action="{{ route('member.friends.unblock'.$s, ['member' => $block->blocked]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-full border border-ink/30 px-4 py-2 text-sm font-medium transition hover:bg-ink hover:text-parchment">{{ __('member.friends.unblock') }}</button>
                </form>
            </div>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('member.friends.empty_blocked') }}</p>
        @endforelse
    </section>
@endsection
