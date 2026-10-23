{{-- Same layout as the PHPRunner e-mail (New_Button40 / New_Button47). --}}
<div style="font-family: Arial, Helvetica, sans-serif">
    <p><img style="height: 30px; width: 170px;" src="https://touro.gr/images/Myatlogo.jpg" alt="My Athens Transfers"></p>
    <table style="border-collapse: collapse; width: 100%;" border="1">
        <tr><td style="color: #1997FC;"><strong><em>Εντολέας:</em></strong> <em>My Athens Transfers IKE - &nbsp;MΗ.T.E: 0261E60000721601</em></td></tr>
    </table>
    <p><strong>Συνολικά Δρομολόγια: {{ $jobs->count() }} - {{ $recipient->VehicleName }}</strong></p>

    @foreach ($jobs as $job)
        <p>
            <strong>Ημερομηνία:</strong> {{ $job->TransferDate?->format('d/m/Y') }}<br>
            <strong>Ώρα Pickup:</strong> {{ $job->TransferTime }}<br>
            <strong>Tύπος Οχήματος:</strong> {{ $job->CarTypeID }}<br>
            <strong>Άτομα:</strong> {{ $job->Pax }}<br>
            <strong>Τύπος:</strong> {{ $job->TransferType }}<br>
            <strong>Σημείο Παραλαβής:</strong> {{ $job->From }}<br>
            <strong>Σημείο Αποβίβασης:</strong> {{ $job->To }}<br>
            <strong>Πληροφορίες:</strong> {{ $job->Flight_Ship_Num_From }}<br>
            <strong>Όνομα επιβάτη:</strong> {{ $job->LeadName }}<br>
            <strong>Τηλέφωνο επιβάτη: </strong>{{ $job->LeadPhone }}<br>
            <strong>Συνοδός - Ξεναγός: </strong>{{ $job->Assistant }}<br>
            <strong>Review Link: </strong>{{ $job->ReviewLink }}<br>
            @if ($recipient->Type === 'Driver')
                <strong>Παρατηρήσεις:</strong> {{ $job->Notes }}<br>
            @else
                <strong>Οδηγός:</strong> {{ $job->Fullname }} - Tel. {{ $job->DriverPhone }}<br>
            @endif
            @if ($withPassengers && filled($job->PassengerName))
                <strong>Επιβάτες:</strong><br>
                {!! nl2br(e($job->PassengerName)) !!}<br>
            @endif
        </p>
    @endforeach

    <p>&nbsp;</p>
    <table style="border-collapse: collapse; width: 100%;" border="1">
        <tr><td style="color: #1997FC;"><strong><em>Τρόπος Πληρωμής:</em></strong> <em>Πληρωμή με πίστωση βάσει ιδιωτικού συμφωνητικού</em></td></tr>
    </table>
    <p>&nbsp;</p>
    <p style="font-size: 10px; color: #940027;"><em><strong>Παρακαλώ μην απαντήσετε στο παρόν μήνυμα.<br>Για απορίες και διευκρινίσεις επικοινωνήστε στο {{ config('dispatch.operations_phone') }}</strong></em></p>
    <p><img style="height: 48px; width: 120px;" src="https://touro.gr/images/Touro_email.jpg" alt="Touro"></p>
</div>
