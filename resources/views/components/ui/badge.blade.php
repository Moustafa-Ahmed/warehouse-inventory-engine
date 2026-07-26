@props(['variant' => 'secondary'])

<span {{ $attributes->class(["badge text-bg-{$variant}"]) }}>{{ $slot }}</span>
