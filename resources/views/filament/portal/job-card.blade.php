@php
    /** @var \App\Models\VTransfer $job */
    $job = $getRecord();
    $phone = filled($job->LeadPhone) ? preg_replace('/[^0-9+]/', '', (string) $job->LeadPhone) : null;
    $tickets = $job->Tickets_Flag !== 'No' ? $job->Tickets_Flag : null;
@endphp
<div class="myat-job">
    <div class="myat-job-head">
        <div>
            <div class="myat-job-time">{{ $job->TransferTime }}</div>
            <div class="myat-job-date">{{ $job->TransferDate?->locale('el')->translatedFormat('D d/m') }}</div>
        </div>
        <div class="myat-job-tags">
            <span class="myat-job-tag">{{ $job->TransferType }}</span>
            <span class="myat-job-tag myat-job-tag-soft">{{ $job->Pax }} άτομα</span>
            @if ($tickets)
                <span class="myat-job-tag myat-job-tag-warn">{{ $tickets }}</span>
            @endif
        </div>
    </div>

    <ol class="myat-job-route">
        <li><span class="myat-job-route-label">Παραλαβή</span>{{ $job->From }}</li>
        <li><span class="myat-job-route-label">Αποβίβαση</span>{{ $job->To }}</li>
    </ol>

    @if (filled($job->Flight_Ship_Num_From))
        <div class="myat-job-line"><span>Πληροφορίες</span>{{ $job->Flight_Ship_Num_From }}</div>
    @endif

    <div class="myat-job-line">
        <span>Επιβάτης</span>{{ $job->LeadName ?: '—' }}
        @if ($phone)
            <a class="myat-job-call" href="tel:{{ $phone }}" x-on:click.stop>📞 {{ $job->LeadPhone }}</a>
        @endif
    </div>

    @if (filled($job->Assistant))
        <div class="myat-job-line"><span>Συνοδός</span>{{ $job->Assistant }}</div>
    @endif

    @if (filled($job->Notes))
        <div class="myat-job-notes">{!! nl2br(e($job->Notes)) !!}</div>
    @endif

    @if (filled($job->VehicleName))
        <div class="myat-job-vehicle">{{ $job->VehicleName }}</div>
    @endif
</div>
