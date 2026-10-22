@props(['compact' => false])

@php
    $options = [
        'none' => __('Plain (no effect)'),
        'eclipse' => __('Eclipse Flare'),
        'crimson' => __('Crimson Veil'),
        'witching' => __('Witching Hour'),
    ];
@endphp

<div
    x-data="{ aura: (function () { try { return localStorage.getItem('aura-theme') || 'none'; } catch (e) { return 'none'; } })() }"
    x-on:aura-changed.window="aura = $event.detail"
    {{ $attributes }}
>
    <flux:dropdown :position="$compact ? 'bottom' : 'top'" :align="$compact ? 'end' : 'start'">
        @if ($compact)
            <flux:button variant="ghost" size="sm" icon="sparkles" :aria-label="__('Background')" />
        @else
            <flux:button variant="ghost" size="sm" icon="sparkles" icon-trailing="chevron-up" class="w-full justify-start">
                {{ __('Background') }}
            </flux:button>
        @endif

        <flux:menu>
            @foreach ($options as $key => $label)
                <flux:menu.item x-on:click="setAura('{{ $key }}')">
                    {{ $label }}
                    <span x-show="aura === '{{ $key }}'" class="ms-2">&#10003;</span>
                </flux:menu.item>
            @endforeach
        </flux:menu>
    </flux:dropdown>
</div>
