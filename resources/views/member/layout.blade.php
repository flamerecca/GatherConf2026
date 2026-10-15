@extends('layouts.app')

@section('content')
    @php
        $isEnglish = app()->getLocale() === 'en';
        $routeBase = trim($__env->yieldContent('route'));
        $zhUrl = trim($__env->yieldContent('zh_url')) ?: route($routeBase);
        $enUrl = trim($__env->yieldContent('en_url')) ?: route($routeBase.'.en');
    @endphp
    <x-subpage-header :switch-url="$isEnglish ? $zhUrl : $enUrl"
                      :switch-label="__('member.switch_label')" :back-label="__('member.back')" />
    <main id="main" tabindex="-1" class="mx-auto max-w-md px-4 py-12">
        <h1 class="text-4xl font-bold tracking-tight">@yield('heading')</h1>
        @hasSection('intro')
            <p class="mt-4 leading-relaxed text-ink/80">@yield('intro')</p>
        @endif
        @if (session('status') || session('link_status'))
            <p class="mt-6 rounded-2xl border border-moss/60 bg-parchment-dark p-4 text-sm" role="status">
                {{ session('status') ?? session('link_status') }}
            </p>
        @endif
        @if ($errors->has('link'))
            <p class="mt-6 rounded-2xl border border-copper p-4 text-sm text-copper" role="alert">{{ $errors->first('link') }}</p>
        @endif
        @yield('member_body')
    </main>
    @include('landing.footer')
@endsection
