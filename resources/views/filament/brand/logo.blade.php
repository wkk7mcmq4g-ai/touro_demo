{{-- The business logo (config/business.php): its SVG mark inlined, so the sidebar and dark mode
     can recolour it (--myat-mark-left), and the wordmark in the page font. --}}
@php
    $mark = @file_get_contents(public_path(config('business.logo.mark'))) ?: '';
    $mark = preg_replace('/<svg\b/', '<svg class="myat-brand-mark" aria-hidden="true"', $mark, 1);
    $word = config('business.wordmark');
@endphp
<span class="myat-brand">
    {!! $mark !!}
    <span class="myat-brand-name">@if (filled($word['prefix']))<span class="myat-brand-my">{{ $word['prefix'] }}</span>@endif{{ $word['main'] }}@if (filled($word['suffix']))<span class="myat-brand-light">{{ $word['suffix'] }}</span>@endif</span>
</span>
