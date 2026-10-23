@php
    /** @var \App\Models\VTransfer $job */
    $job = $getRecord();
    $phone = filled($job->LeadPhone) ? preg_replace('/[^0-9+]/', '', (string) $job->LeadPhone) : null;
    $tickets = $job->Tickets_Flag !== 'No' ? $job->Tickets_Flag : null;
    // The job's number in the day, earliest first (v_transfers row_num), out of the day's total.
    static $perDay = [];
    $day = $job->TransferDate?->toDateString();
    $perDay[$day] ??= \App\Models\VTransfer::query()->where('username', $job->username)->whereDate('TransferDate', $day)->count();
@endphp
<div class="myat-job">
    <div class="myat-job-top">
        <span class="myat-job-vehicle">{{ $job->VehicleName ?: '—' }}</span>
        <span class="myat-job-id">#{{ $job->TransferID }}</span>
    </div>

    <div class="myat-job-head">
        <div class="myat-job-count" title="Δρομολόγιο {{ $job->row_num }} από {{ $perDay[$day] }}">
            <span>{{ $job->row_num }}</span><small>/{{ $perDay[$day] }}</small>
        </div>
        <div class="myat-job-when">
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
</div>
