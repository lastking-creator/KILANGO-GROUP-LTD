@props(['label', 'value', 'hint' => null, 'tone' => null])

@php
    $toneClass = match ($tone) {
        'good' => 'text-green-600 dark:text-green-400',
        'warn' => 'text-amber-600 dark:text-amber-400',
        'bad' => 'text-red-600 dark:text-red-400',
        default => '',
    };
@endphp

<div {{ $attributes->class('rounded-xl border border-neutral-200 p-4 dark:border-neutral-700') }}>
    <div class="text-sm text-neutral-500">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold {{ $toneClass }}">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs text-neutral-500">{{ $hint }}</div>
    @endif
</div>
