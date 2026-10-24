@php
    /** @var \App\Models\FuelRegister $receipt */
    $receipt = $getRecord();
    $status = $receipt->ApprovedStatus;
    [$label, $tone] = match (true) {
        blank($status) => ['Σε έλεγχο', 'amber'],
        $status === 'Rejected' => ['Απορρίφθηκε', 'rose'],
        default => ['Εγκρίθηκε', 'green'],
    };
    $files = \App\Support\LegacyFiles::parse($receipt->Files);
    $vehicle = \App\Support\Lookups::vehicles()[$receipt->VehicleID] ?? null;
@endphp
<div class="myat-fuel">
    <div class="myat-fuel-top">
        <div>
            <div class="myat-fuel-amount">{{ \Illuminate\Support\Number::currency((float) $receipt->FuelAmount, 'EUR', 'el') }}</div>
            <div class="myat-fuel-date">{{ $receipt->FuelDate?->locale('el')->translatedFormat('D d/m/Y') }}</div>
        </div>
        <span class="myat-fuel-status myat-tone-{{ $tone }}">{{ $label }}</span>
    </div>
    <div class="myat-fuel-meta">
        @if ($vehicle)
            <span class="myat-fuel-vehicle">{{ $vehicle }}</span>
        @endif
        @if ($files !== [])
            @foreach ($files as $file)
                <a class="myat-fuel-file" href="{{ \App\Support\LegacyFiles::url($file['name']) }}" target="_blank" rel="noopener" x-on:click.stop>
                    <x-filament::icon icon="heroicon-m-paper-clip" class="myat-fuel-file-icon" />{{ \Illuminate\Support\Str::limit($file['usrName'] ?? basename($file['name']), 22) }}
                </a>
            @endforeach
        @else
            <span class="myat-fuel-nofile">Χωρίς φωτογραφία</span>
        @endif
    </div>
    @if ($status === 'Rejected' && filled($receipt->ApprovedReason))
        <div class="myat-fuel-reason">{{ $receipt->ApprovedReason }}</div>
    @endif
</div>
