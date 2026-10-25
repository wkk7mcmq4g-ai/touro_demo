@php
    /** One job as a card. $job: VTransfer; $showNotes, $showDriver, $withPassengers: bool. */
    $type = (string) $job->TransferType;
    $typeBg = \App\Support\Lookups::color('TransferType', $type) ?: '#e2e8f0';
    $typeBg = preg_match('/^#?[0-9a-fA-F]{3,8}$|^[a-zA-Z]+$/', $typeBg) ? $typeBg : '#e2e8f0';
    $typeFg = \App\Filament\Resources\Transfers\Tables\TransferCellColors::textOn($typeBg);
    $tel = fn (?string $phone): string => preg_replace('/[^\d+]/', '', (string) $phone);
    $car = trim(implode(' · ', array_filter([(string) $job->CarTypeID, filled($job->Pax) ? $job->Pax.' άτομα' : null])));
    $isTour = $type === 'Tour';
    $map = filled($job->From) ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $job->From) : null;
    $rows = array_filter([
        'Πτήση / πλοίο' => $job->Flight_Ship_Num_From,
        'Επιβάτης' => $job->LeadName,
        'Ξεναγός' => $job->Assistant,
    ], 'filled');
    $notes = trim((string) $job->Notes);
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="mx-card" style="border:1px solid #e2e8f0;border-radius:14px;border-collapse:separate;margin:0 0 14px">
<tr>
    <td class="mx-time" valign="top" style="width:84px;padding:16px 0 16px 16px;font-size:24px;font-weight:800;color:#062a4a;font-variant-numeric:tabular-nums">
        <span class="mx-t" style="color:#062a4a">{{ $job->TransferTime }}</span>
        <div class="mx-m" style="font-size:11px;font-weight:700;letter-spacing:.04em;color:#64748b;margin-top:2px">PICKUP</div>
    </td>
    <td valign="top" style="padding:16px 16px 16px 8px">
        @if ($type !== '')
            <span style="display:inline-block;background:{{ $typeBg }};color:{{ $typeFg }};border-radius:6px;padding:2px 8px;font-size:12px;font-weight:700">{{ $type }}</span>
        @endif
        @if ($car !== '')
            <span style="display:inline-block;background:#e0f2fe;color:#075985;border-radius:6px;padding:2px 8px;font-size:12px;font-weight:700">{{ $car }}</span>
        @endif

        <div class="mx-t" style="margin-top:10px;font-size:16px;font-weight:700;line-height:1.35;color:#0f172a">{{ $job->From }}</div>
        @if (! $isTour || filled($job->To))
            <div class="mx-m" style="font-size:13px;color:#94a3b8;margin:2px 0">&darr;</div>
            <div class="mx-t" style="font-size:16px;font-weight:700;line-height:1.35;color:#0f172a">{{ $job->To }}</div>
        @endif

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px;font-size:14px;line-height:1.45">
            @foreach ($rows as $label => $value)
                <tr>
                    <td class="mx-m mx-lbl" valign="top" style="color:#64748b;width:128px;padding:3px 8px 3px 0">{{ $label }}</td>
                    <td class="mx-t" valign="top" style="color:#0f172a;padding:3px 0">
                        {{ $value }}
                        @if ($label === 'Επιβάτης' && filled($job->LeadPhone))
                            &middot; <a href="tel:{{ $tel($job->LeadPhone) }}" class="mx-link" style="color:#0369a1;font-weight:700;text-decoration:none;white-space:nowrap">{{ $job->LeadPhone }}</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            @if (blank($job->LeadName) && filled($job->LeadPhone))
                <tr>
                    <td class="mx-m mx-lbl" style="color:#64748b;width:128px;padding:3px 8px 3px 0">Τηλέφωνο</td>
                    <td style="padding:3px 0"><a href="tel:{{ $tel($job->LeadPhone) }}" class="mx-link" style="color:#0369a1;font-weight:700;text-decoration:none;white-space:nowrap">{{ $job->LeadPhone }}</a></td>
                </tr>
            @endif
            @if ($showDriver && filled($job->Fullname))
                <tr>
                    <td class="mx-m mx-lbl" style="color:#64748b;width:128px;padding:3px 8px 3px 0">Οδηγός</td>
                    <td class="mx-t" style="color:#0f172a;padding:3px 0">
                        {{ $job->Fullname }}
                        @if (filled($job->DriverPhone))
                            &middot; <a href="tel:{{ $tel($job->DriverPhone) }}" class="mx-link" style="color:#0369a1;font-weight:700;text-decoration:none;white-space:nowrap">{{ $job->DriverPhone }}</a>
                        @endif
                    </td>
                </tr>
            @endif
        </table>

        @if ($showNotes && $notes !== '')
            <div style="margin-top:10px;background:#fff7e6;border-left:3px solid #f59e0b;border-radius:6px;padding:8px 10px;font-size:13px;line-height:1.5;color:#7c4a03">{!! nl2br(e($notes)) !!}</div>
        @endif

        @if (($withPassengers ?? false) && filled($job->PassengerName))
            <div class="mx-soft mx-t" style="margin-top:10px;background:#f8fafc;border-radius:6px;padding:8px 10px;font-size:13px;line-height:1.5;color:#334155">
                <strong>Επιβάτες</strong><br>{!! nl2br(e($job->PassengerName)) !!}
            </div>
        @endif

        @if ($map || filled($job->ReviewLink))
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px"><tr>
                @if ($map)
                    <td style="background:{{ config('business.colors.primary') }};border-radius:8px"><a href="{{ $map }}" style="display:inline-block;padding:8px 14px;font-size:13px;font-weight:700;color:#ffffff;text-decoration:none;white-space:nowrap">Χάρτης παραλαβής</a></td>
                @endif
                @if ($map && filled($job->ReviewLink))<td width="8" style="font-size:0">&nbsp;</td>@endif
                @if (filled($job->ReviewLink))
                    <td style="border:1px solid #cbd5e1;border-radius:8px"><a href="{{ $job->ReviewLink }}" class="mx-t" style="display:inline-block;padding:7px 14px;font-size:13px;font-weight:700;color:#0f172a;text-decoration:none;white-space:nowrap">Review link</a></td>
                @endif
            </tr></table>
        @endif
    </td>
</tr>
</table>
