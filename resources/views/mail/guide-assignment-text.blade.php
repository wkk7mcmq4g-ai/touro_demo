Εντολή ανάθεσης ξενάγησης (το PDF επισυνάπτεται)

Ημερομηνία: {{ $job->TransferDate?->format('d/m/Y') }}
Ώρα pickup: {{ $job->TransferTime }}
Άτομα: {{ $job->Pax }}
Τύπος: {{ $job->TransferType }}
Σημείο παραλαβής: {{ $job->From }}
Σημείο αποβίβασης: {{ $job->To }}
Πληροφορίες: {{ $job->Flight_Ship_Num_From }}
Όνομα επιβάτη: {{ $job->LeadName }}
Τηλέφωνο επιβάτη: {{ $job->LeadPhone }}
Συνοδός - ξεναγός: {{ $job->Assistant }}
Οδηγός: {{ $job->Fullname }} {{ $job->DriverPhone }}

Παρακαλούμε μην απαντήσετε σε αυτό το μήνυμα. Για απορίες: {{ config('business.operations.phone') }}
