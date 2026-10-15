@extends('member.layout')

@section('route', 'member.bottles')
@section('robots', 'noindex')
@section('title', __('bottles.index.title'))
@section('description', __('bottles.index.title'))
@section('heading', __('bottles.index.heading'))
@section('intro', __('bottles.index.intro'))

@section('member_body')
    @php($s = app()->getLocale() === 'en' ? '.en' : '')

    <section class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @error('pick')
            <p class="rounded-2xl border border-copper p-4 text-sm text-copper" role="alert">{{ $message }}</p>
        @enderror
        <div class="flex flex-wrap gap-4">
            <a href="{{ route('member.bottles.create'.$s) }}"
               class="inline-block rounded-full bg-moss px-6 py-3 font-semibold text-on-accent shadow transition hover:bg-ink">
                {{ __('bottles.index.throw') }}
            </a>
            <form method="POST" action="{{ route('member.bottles.pick'.$s) }}">
                @csrf
                <x-member-submit>{{ __('bottles.index.pick') }}</x-member-submit>
            </form>
        </div>
        <p class="text-sm text-ink/70">
            {{ __('bottles.index.throws_left', ['count' => $throwsLeft]) }} ·
            {{ __('bottles.index.picks_left', ['count' => $picksLeft]) }}
        </p>
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('bottles.index.received') }}</h2>
        @forelse ($received as $bottle)
            <a href="{{ route('member.bottles.show'.$s, ['bottle' => $bottle]) }}" data-bottle-status="{{ $bottle->status() }}"
               class="mt-4 block rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <span class="font-semibold">{{ __('bottles.status.'.$bottle->status()) }}</span>
                <span class="ml-2 text-sm text-moss underline underline-offset-4">{{ __('bottles.index.view') }}</span>
            </a>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('bottles.index.empty_received') }}</p>
        @endforelse
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('bottles.index.sent') }}</h2>
        @forelse ($sent as $bottle)
            <a href="{{ route('member.bottles.show'.$s, ['bottle' => $bottle]) }}" data-bottle-status="{{ $bottle->status() }}"
               class="mt-4 block rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <span class="font-semibold">{{ __('bottles.status.'.$bottle->status()) }}</span>
                <span class="ml-2 text-sm text-moss underline underline-offset-4">{{ __('bottles.index.view') }}</span>
            </a>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('bottles.index.empty_sent') }}</p>
        @endforelse
    </section>
@endsection
