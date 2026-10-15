@extends('member.layout')

@section('route', 'member.tasks')
@section('robots', 'noindex')
@section('title', __('carrots.list.title'))
@section('description', __('carrots.list.title'))
@section('heading', __('carrots.list.heading'))

@section('member_body')
    <p data-carrots="{{ $member->carrots }}" @class(['mt-6 text-lg', 'font-semibold' => $member->carrots >= 0, 'text-red-600 dark:text-red-400 font-bold' => $member->carrots < 0])>
        🥕 {{ __('carrots.balance', ['count' => $member->carrots]) }}
    </p>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('carrots.list.site_heading') }}</h2>
        @foreach ($siteTasks as $row)
            <div data-site-task="{{ $row['code'] }}" data-state="{{ $row['done'] ? 'done' : 'todo' }}" data-reward="{{ $row['reward'] }}"
                 class="mt-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ __('carrots.site.'.$row['code'].'.name') }}</p>
                <p class="text-sm text-ink/70">{{ __('carrots.site.'.$row['code'].'.description') }}</p>
                <p class="mt-2 text-sm">
                    🥕 × {{ $row['reward'] }} ·
                    {{ $row['done'] ? __('carrots.list.done') : __('carrots.list.todo') }}
                </p>
            </div>
        @endforeach
    </section>

    <section class="mt-8">
        <h2 class="text-xl font-bold text-moss">{{ __('carrots.list.field_heading') }}</h2>
        <p class="mt-2 text-sm text-ink/70">{{ __('carrots.list.field_intro') }}</p>
        @forelse ($fieldTasks as $row)
            <div data-field-task="{{ $row['task']->id }}" data-state="{{ $row['done'] ? 'done' : 'todo' }}"
                 class="mt-4 rounded-2xl border border-ink/15 bg-parchment-dark p-4">
                <p class="font-semibold">{{ $row['task']->name }}</p>
                @if ($row['task']->description)
                    <p class="text-sm text-ink/70">{{ $row['task']->description }}</p>
                @endif
                <p class="mt-2 text-sm">
                    🥕 × {{ $row['task']->reward }} ·
                    {{ $row['done'] ? __('carrots.list.done') : __('carrots.list.todo') }}
                </p>
            </div>
        @empty
            <p class="mt-4 text-sm text-ink/70">{{ __('carrots.list.field_empty') }}</p>
        @endforelse
    </section>
@endsection
