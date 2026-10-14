@extends('member.layout')

@section('route', 'member.login')
@section('robots', 'noindex')
@section('title', __('member.login.title'))
@section('description', __('member.login.description'))
@section('heading', __('member.login.heading'))

@section('member_body')
    @php($s = app()->getLocale() === 'en' ? '.en' : '')
    <div class="mt-8 flex flex-wrap items-center gap-4">
        <a href="{{ route('member.register'.$s) }}"
           class="inline-block rounded-full bg-moss px-8 py-3 font-semibold text-on-accent shadow transition hover:bg-ink">
            {{ __('member.login.register_link') }}
        </a>
        <span class="text-sm text-ink/70">{{ __('member.login.register_prompt') }}</span>
    </div>
    <form method="POST" action="{{ route('member.login.store'.$s) }}"
          class="mt-6 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <h2 class="text-xl font-bold text-moss">{{ __('member.login.password_heading') }}</h2>
        <x-member-field name="email" type="email" :label="__('member.fields.email')" autocomplete="email" required />
        <x-member-field name="password" type="password" :label="__('member.fields.password')" autocomplete="current-password" required />
        <p class="text-xs text-ink/70">{{ __('member.login.no_password_hint') }}</p>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="size-4 accent-moss">
            {{ __('member.login.remember') }}
        </label>
        <div class="flex items-center justify-between gap-4">
            <x-member-submit>{{ __('member.login.submit') }}</x-member-submit>
            <a href="{{ route('member.password.request'.$s) }}" class="text-sm font-medium text-moss underline underline-offset-4">{{ __('member.login.forgot') }}</a>
        </div>
    </form>

    <form method="POST" action="{{ route('member.login.link.request'.$s) }}"
          class="mt-8 space-y-6 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6">
        @csrf
        <h2 class="text-xl font-bold text-moss">{{ __('member.login.link_heading') }}</h2>
        <p class="text-sm leading-relaxed text-ink/80">{{ __('member.login.link_intro') }}</p>
        <x-member-field name="email" type="email" :label="__('member.fields.email')" autocomplete="email" required />
        <x-member-submit>{{ __('member.login.link_submit') }}</x-member-submit>
    </form>

@endsection
