Χαίρετε,

Παρακαλώ βρείτε συνημμένα τα παραρτήματα 1 & 2 με τα στοιχεία επιβατών, οχήματος και οδηγού.

Ημερομηνία: {{ $job->TransferDate?->format('d/m/Y') }}
Ώρα: {{ $job->TransferTime }}
Πλοίο: {{ $job->Flight_Ship_Num_From }}
Διαδρομή: {{ $job->From }} → {{ $job->To }}

Ευχαριστούμε εκ των προτέρων.

Με εκτίμηση,
{{ \App\Support\Business::signatureText() }}
