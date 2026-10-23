{{-- Under the driver's jobs: tomorrow at a glance, and the Διαβάστηκαν confirmation (PHPRunner ReadReceipt). --}}
<section class="myat-tomorrow" aria-labelledby="myat-tomorrow-title">
    <div class="myat-tomorrow-head">
        <h2 id="myat-tomorrow-title">Αύριο</h2>
        <span class="myat-tomorrow-date">
            <x-filament::icon icon="heroicon-m-calendar" class="myat-tomorrow-date-icon" />{{ $date }}
        </span>
    </div>

    @if ($count > 0)
        <p class="myat-tomorrow-line">
            <b>{{ $count }}</b> {{ $count === 1 ? 'δρομολόγιο' : 'δρομολόγια' }} · πρώτο <b>{{ $first }}</b>
        </p>
        @if ($read)
            <p class="myat-tomorrow-read">
                <x-filament::icon icon="heroicon-m-check-circle" class="myat-tomorrow-read-icon" />
                Επιβεβαιώσατε ότι το διαβάσατε
            </p>
        @endif
        <div class="myat-tomorrow-action">{{ $this->readReceiptAction }}</div>
    @else
        <p class="myat-tomorrow-line">Δεν υπάρχουν δρομολόγια αύριο.</p>
    @endif
</section>
