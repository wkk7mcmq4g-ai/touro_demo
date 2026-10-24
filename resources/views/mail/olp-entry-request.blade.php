{{-- Entry request to Piraeus port (PHPRunner New_Button66): a short formal letter, forms attached. --}}
@php $day = $job->TransferDate?->copy()->locale('el'); @endphp
<x-mail.layout
    title="Αίτηση άδειας εισόδου"
    eyebrow="Λιμάνι Πειραιά"
    :pills="array_values(array_filter([$day?->format('d/m/Y'), $job->TransferTime, $job->Flight_Ship_Num_From]))"
    :preheader="'Άδεια εισόδου παραλαβής & αποβίβασης '.($day?->format('d/m/Y') ?? '').' · '.$job->Flight_Ship_Num_From"
>
    <div class="mx-t" style="font-size:15px;line-height:1.7;color:#0f172a">
        <p style="margin:0 0 12px">Χαίρετε,</p>
        <p style="margin:0 0 12px">Παρακαλώ βρείτε συνημμένα τα παραρτήματα 1 &amp; 2 με τα στοιχεία επιβατών, οχήματος και οδηγού για την παρακάτω μετακίνηση.</p>
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="mx-soft" style="background:#f8fafc;border-radius:10px;margin:4px 0 16px">
        <tr><td style="padding:12px 14px;font-size:14px;line-height:1.7">
            @foreach (array_filter([
                'Ημερομηνία' => $day?->format('d/m/Y'),
                'Ώρα' => $job->TransferTime,
                'Πλοίο' => $job->Flight_Ship_Num_From,
                'Διαδρομή' => trim($job->From.' → '.$job->To, ' →'),
                'Όχημα' => $job->LicencePlate ? trim($job->VehicleName.' · '.$job->LicencePlate) : $job->VehicleName,
            ], 'filled') as $label => $value)
                <span class="mx-m" style="color:#64748b">{{ $label }}:</span> <strong class="mx-t" style="color:#0f172a">{{ $value }}</strong><br>
            @endforeach
        </td></tr>
    </table>
    <div class="mx-t" style="font-size:15px;line-height:1.7;color:#0f172a">
        <p style="margin:0 0 12px">Ευχαριστούμε εκ των προτέρων.</p>
        <p style="margin:0 0 4px">Με εκτίμηση,</p>
        <div style="margin:0 0 12px">{!! config('dispatch.signature_html') !!}</div>
    </div>
</x-mail.layout>
