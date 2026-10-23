@php
    /** @var \App\Models\VTransfer $job */
    $job = $getRecord();
    $phone = filled($job->LeadPhone) ? preg_replace('/[^0-9+]/', '', (string) $job->LeadPhone) : null;
    $tickets = $job->Tickets_Flag !== 'No' ? $job->Tickets_Flag : null;
    $time = substr((string) $job->TransferTime, 0, 5);
    // The job's number in the day, earliest first (v_transfers row_num), out of the day's total.
    static $perDay = [];
    $day = $job->TransferDate?->toDateString();
    $perDay[$day] ??= \App\Models\VTransfer::query()->where('username', $job->username)->whereDate('TransferDate', $day)->count();
    // Today's next job is shown large (the "hero"); every other job is a compact row.
    $isNext = (int) \App\Filament\Portal\Resources\MyJobs\MyJobResource::nextJobId() === (int) $job->TransferID;
    $done = $job->Status === 'Completed';
    $minutes = $isNext ? (int) now()->diffInMinutes(\Illuminate\Support\Carbon::parse(today()->toDateString().' '.$time)) : null;
@endphp

@if ($isNext)
    <div class="myat-hero">
        <div class="myat-hero-top">
            <span class="myat-hero-label">
                <x-filament::icon icon="heroicon-m-bolt" class="myat-hero-label-icon" />
                Επόμενο δρομολόγιο
            </span>
            <span class="myat-hero-countdown">σε {{ $minutes >= 60 ? intdiv($minutes, 60).'ώ '.($minutes % 60).'΄' : $minutes.'΄' }}</span>
        </div>

        <div class="myat-hero-ids">
            <span class="myat-hero-vehicle">{{ $job->VehicleName ?: '—' }}</span>
            <span class="myat-hero-id">#{{ $job->TransferID }}</span>
        </div>

        <div class="myat-hero-when">
            <div>
                <div class="myat-hero-time">{{ $time }}</div>
                <div class="myat-hero-sub">{{ $job->TransferDate?->locale('el')->translatedFormat('D d/m') }} · δρομολόγιο {{ $job->row_num }}/{{ $perDay[$day] }}</div>
            </div>
            <div class="myat-hero-tags">
                <span class="myat-hero-tag myat-hero-tag-mint">{{ $job->TransferType }}</span>
                <span class="myat-hero-tag">{{ $job->Pax }} άτομα</span>
                @if ($tickets)
                    <span class="myat-hero-tag myat-hero-tag-warn">{{ $tickets }}</span>
                @endif
            </div>
        </div>

        <ol class="myat-hero-route">
            <li><span>Παραλαβή</span>{{ $job->From }}</li>
            <li><span>Αποβίβαση</span>{{ $job->To }}</li>
        </ol>

        @if (filled($job->Flight_Ship_Num_From))
            <div class="myat-hero-line"><span>Πληροφορίες</span>{{ $job->Flight_Ship_Num_From }}</div>
        @endif

        <div class="myat-hero-pax">
            <span class="myat-hero-line-label">Επιβάτης</span>
            <div class="myat-hero-pax-name">{{ $job->LeadName ?: '—' }}</div>
            @if ($phone)
                <a class="myat-hero-call" href="tel:{{ $phone }}" x-on:click.stop>
                    <x-filament::icon icon="heroicon-s-phone" class="myat-hero-call-icon" />
                    <span>{{ $job->LeadPhone }}</span>
                </a>
            @elseif (filled($job->LeadPhone))
                <div class="myat-hero-sub">{{ $job->LeadPhone }}</div>
            @endif
        </div>

        @if (filled($job->Assistant))
            <div class="myat-hero-line"><span>Συνοδός</span>{{ $job->Assistant }}</div>
        @endif

        @if (filled($job->Notes))
            <div class="myat-hero-notes">{!! nl2br(e($job->Notes)) !!}</div>
        @endif
    </div>
@else
    <div @class(['myat-row', 'is-done' => $done])>
        <div class="myat-row-mark" title="Δρομολόγιο {{ $job->row_num }} από {{ $perDay[$day] }}">
            @if ($done)
                <x-filament::icon icon="heroicon-m-check" class="myat-row-check" />
            @else
                <span>{{ $job->row_num }}</span>
            @endif
        </div>
        <div class="myat-row-main">
            <div class="myat-row-head">
                <span class="myat-row-time">{{ $time }}</span>
                <span class="myat-row-id">#{{ $job->TransferID }}</span>
                <span class="myat-row-vehicle">{{ $job->VehicleName ?: '—' }}</span>
                @unless ($job->TransferDate?->isToday())
                    <span class="myat-row-date">{{ $job->TransferDate?->locale('el')->translatedFormat('D d/m') }}</span>
                @endunless
            </div>
            <div class="myat-row-route">{{ $job->From ?: '—' }} → {{ $job->To ?: '—' }}</div>
            @if (filled($job->Flight_Ship_Num_From))
                <div class="myat-row-info">{{ $job->Flight_Ship_Num_From }}</div>
            @endif
            <div class="myat-row-pax">
                {{ $job->LeadName ?: '—' }}
                @if ($phone)
                    <a class="myat-row-phone" href="tel:{{ $phone }}" x-on:click.stop>
                        <x-filament::icon icon="heroicon-m-phone" class="myat-row-phone-icon" />{{ $job->LeadPhone }}
                    </a>
                @elseif (filled($job->LeadPhone))
                    · {{ $job->LeadPhone }}
                @endif
            </div>
            @if (filled($job->Notes))
                <div class="myat-row-notes">{!! nl2br(e($job->Notes)) !!}</div>
            @endif
        </div>
        <div class="myat-row-side">
            @if ($done)
                <span class="myat-row-chip myat-row-chip-done">Ολοκληρώθηκε</span>
            @endif
            <span class="myat-row-chip">{{ $job->TransferType }}</span>
            <span class="myat-row-chip">{{ $job->Pax }} άτομα</span>
            @if ($tickets)
                <span class="myat-row-chip myat-row-chip-warn">{{ $tickets }}</span>
            @endif
        </div>
    </div>
@endif
