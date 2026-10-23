@php
    /** @var \App\Models\VTransfer $job */
    $job = $getRecord();
    $phone = filled($job->LeadPhone) ? preg_replace('/[^0-9+]/', '', (string) $job->LeadPhone) : null;
    $tickets = $job->Tickets_Flag !== 'No' ? $job->Tickets_Flag : null;
    // The job's number in the day, earliest first (v_transfers row_num), out of the day's total.
    static $perDay = [];
    $day = $job->TransferDate?->toDateString();
    $perDay[$day] ??= \App\Models\VTransfer::query()->where('username', $job->username)->whereDate('TransferDate', $day)->count();
    // Today: the next job to drive stands out, the ones already started step back.
    static $nextToday = false;
    $isToday = $job->TransferDate?->isToday();
    $time = substr((string) $job->TransferTime, 0, 5);
    if ($isToday && $nextToday === false) {
        $nextToday = \App\Models\VTransfer::query()->where('username', $job->username)->whereDate('TransferDate', today())
            ->where('TransferTime', '>=', now()->format('H:i'))->orderBy('TransferTime_sort')->value('TransferID');
    }
    $isNext = $isToday && $nextToday && (int) $nextToday === (int) $job->TransferID;
    $isPast = $isToday && ! $isNext && $time < now()->format('H:i');
    $minutes = $isNext ? (int) now()->diffInMinutes(\Illuminate\Support\Carbon::parse(today()->toDateString().' '.$time)) : null;
@endphp
<div @class(['myat-job', 'is-next' => $isNext, 'is-past' => $isPast])>
    @if ($isNext)
        <div class="myat-job-nextbar">
            <span>Επόμενο δρομολόγιο</span>
            <b>σε {{ $minutes >= 60 ? intdiv($minutes, 60).'ώ '.($minutes % 60).'΄' : $minutes.'΄' }}</b>
        </div>
    @endif
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
            @if ($job->Status === 'Completed')
                <span class="myat-job-tag myat-job-tag-done">Ολοκληρώθηκε</span>
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

    <div class="myat-job-line myat-job-pax">
        <div>
            <span>Επιβάτης</span>{{ $job->LeadName ?: '—' }}
            @if (filled($job->LeadPhone))
                <div class="myat-job-phone">{{ $job->LeadPhone }}</div>
            @endif
        </div>
        @if ($phone)
            <a class="myat-job-call" href="tel:{{ $phone }}" x-on:click.stop aria-label="Κλήση {{ $job->LeadName }} {{ $job->LeadPhone }}">
                <x-filament::icon icon="heroicon-s-phone" class="myat-job-call-icon" />
                <b>Κλήση</b>
            </a>
        @endif
    </div>

    @if (filled($job->Assistant))
        <div class="myat-job-line"><span>Συνοδός</span>{{ $job->Assistant }}</div>
    @endif

    @if (filled($job->Notes))
        <div class="myat-job-notes">{!! nl2br(e($job->Notes)) !!}</div>
    @endif
</div>
