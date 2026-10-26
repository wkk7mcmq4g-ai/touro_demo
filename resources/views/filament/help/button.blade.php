{{-- "?" next to a screen's buttons: opens this screen's help article in a side panel (help/drawer). --}}
<button type="button" class="myat-help-btn" title="Βοήθεια για αυτή την οθόνη"
    x-data x-on:click="$dispatch('myat-help', { url: @js($url) })">
    <x-filament::icon icon="heroicon-m-question-mark-circle" />
    <span>Βοήθεια</span>
</button>
