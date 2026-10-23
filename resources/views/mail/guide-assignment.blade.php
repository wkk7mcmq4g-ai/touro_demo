<p style="font-family:Arial,Helvetica,sans-serif">
<strong>Ημερομηνία:</strong> {{ $job->TransferDate?->format('d/m/Y') }}<br>
<strong>Ωρα Pickup:</strong> {{ $job->TransferTime }}<br>
<strong>Άτομα:</strong> {{ $job->Pax }}<br>
<strong>Τύπος:</strong> {{ $job->TransferType }}<br>
<strong>Σημείο Παραλαβής:</strong> {{ $job->From }}<br>
<strong>Σημείο Αποβίβασης:</strong> {{ $job->To }}<br>
<strong>Πληροφορίες:</strong> {{ $job->Flight_Ship_Num_From }}<br>
<strong>Όνομα επιβάτη:</strong> {{ $job->LeadName }}<br>
<strong>Τηλέφωνο επιβάτη:</strong> {{ $job->LeadPhone }}<br>
<strong>Συνοδός - Ξεναγός:</strong> {{ $job->Assistant }}<br>
<strong>Οδηγός:</strong> {{ $job->Fullname }}- Tel.{{ $job->DriverPhone }}
</p>
<p style="font-size:10px;color:#940027;font-family:Arial,Helvetica,sans-serif"><em><strong>Παρακαλώ μην απαντήσετε στο παρόν μήνυμα.<br>
Για απορίες και διευκρινίσεις επικοινωνήστε στο {{ config('dispatch.operations_phone') }}</strong></em></p>
