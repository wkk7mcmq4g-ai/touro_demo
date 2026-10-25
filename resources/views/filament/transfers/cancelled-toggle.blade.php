{{-- Transfers toolbar: show or leave out cancelled transfers (ListTransfers::toggleCancelled). --}}
@php($on = $this->showsCancelled())
<button type="button" wire:click="toggleCancelled" wire:loading.attr="disabled" wire:target="toggleCancelled"
        role="switch" aria-checked="{{ $on ? 'true' : 'false' }}"
        @class(['myat-quick-toggle', 'is-on' => $on])
        title="{{ $on ? 'Απόκρυψη ακυρωμένων' : 'Εμφάνιση ακυρωμένων' }}">
    <span class="myat-quick-toggle-track" aria-hidden="true"><span></span></span>
    Ακυρωμένα
    <span class="myat-quick-toggle-count">{{ $this->cancelledCount() }}</span>
</button>
