@extends('member.layout')

@section('route', 'member.tasks')
@section('robots', 'noindex')
@section('title', __('carrots.result.title'))
@section('description', __('carrots.result.title'))
@section('heading', __('carrots.result.heading'))

@section('member_body')
    @php($s = app()->getLocale() === 'en' ? '.en' : '')
    <div class="mt-8 space-y-4 rounded-2xl border-4 border-moss/60 bg-parchment-dark p-6" data-task-result="{{ $state }}">
        <p class="text-lg font-semibold">{{ $task->name }}</p>
        <p>
            @if ($state === 'earned')
                {{ __('carrots.result.earned', ['reward' => $task->reward]) }}
            @elseif ($state === 'done')
                {{ __('carrots.result.done') }}
            @else
                {{ __('carrots.result.closed') }}
            @endif
        </p>
        <p class="text-sm text-ink/80">{{ __('carrots.balance', ['count' => $carrots]) }}</p>
        <a href="{{ route('member.tasks'.$s) }}" class="font-semibold text-moss underline underline-offset-4">{{ __('carrots.result.to_list') }}</a>
    </div>
@endsection
