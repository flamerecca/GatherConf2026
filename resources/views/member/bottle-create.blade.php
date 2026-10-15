@extends('member.layout')

@section('route', 'member.bottles.create')
@section('robots', 'noindex')
@section('title', __('bottles.create.title'))
@section('description', __('bottles.create.title'))
@section('heading', __('bottles.create.heading'))
@section('intro', __('bottles.create.hint'))

@section('member_body')
    @php($s = app()->getLocale() === 'en' ? '.en' : '')
    <form method="POST" action="{{ route('member.bottles.store'.$s) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <div>
            <label for="body" class="block text-sm font-semibold">{{ __('bottles.create.body') }}</label>
            <textarea id="body" name="body" rows="6" maxlength="300" required
                      class="mt-2 w-full rounded-xl border border-ink/30 bg-parchment px-4 py-3 focus:border-moss focus:outline-none focus:ring-2 focus:ring-moss/40">{{ old('body') }}</textarea>
            @error('body')
                <p class="mt-2 text-sm text-copper" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="agree_conduct" value="1" required class="mt-1">
                <span>
                    {{ __('bottles.create.agree') }}，
                    <a href="{{ route('conduct'.$s) }}" target="_blank" rel="noopener"
                       class="font-semibold text-moss underline underline-offset-4">{{ __('bottles.create.conduct_link') }}</a>
                </span>
            </label>
            @error('agree_conduct')
                <p class="mt-2 text-sm text-copper" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <x-member-submit>{{ __('bottles.create.submit') }}</x-member-submit>
    </form>
@endsection
