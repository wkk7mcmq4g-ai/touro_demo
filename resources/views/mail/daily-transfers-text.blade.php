{{ config('business.name') }} · Εντολή δρομολογίων
{{ $recipient->TransferDate->copy()->locale('el')->translatedFormat('l j F Y') }} · {{ $jobs->count() }} δρομολόγια{{ $recipient->Type === 'Driver' && filled($recipient->VehicleName) ? ' · '.$recipient->VehicleName : '' }}

@foreach ($jobs as $job)
{{ $job->TransferTime }} · {{ $job->TransferType }}{{ filled($job->CarTypeID) ? ' · '.$job->CarTypeID : '' }}{{ filled($job->Pax) ? ' · '.$job->Pax.' άτομα' : '' }}
{{ $job->From }} → {{ $job->To }}
@if (filled($job->Flight_Ship_Num_From))
Πτήση / πλοίο: {{ $job->Flight_Ship_Num_From }}
@endif
@if (filled($job->LeadName) || filled($job->LeadPhone))
Επιβάτης: {{ trim($job->LeadName.' '.$job->LeadPhone) }}
@endif
@if (filled($job->Assistant))
Συνοδός / ξεναγός: {{ $job->Assistant }}
@endif
@if ($recipient->Type === 'Driver' && filled($job->Notes))
Παρατηρήσεις: {{ $job->Notes }}
@elseif ($recipient->Type !== 'Driver' && filled($job->Fullname))
Οδηγός: {{ $job->Fullname }} {{ $job->DriverPhone }}
@endif
@if ($withPassengers && filled($job->PassengerName))
Επιβάτες:
{{ $job->PassengerName }}
@endif
@if (filled($job->ReviewLink))
Review link: {{ $job->ReviewLink }}
@endif

@endforeach
Εντολέας: {{ \App\Support\Business::legalLine() }}
Τρόπος πληρωμής: Πληρωμή με πίστωση βάσει ιδιωτικού συμφωνητικού

Παρακαλούμε μην απαντήσετε σε αυτό το μήνυμα. Για απορίες: {{ config('business.operations.phone') }}
