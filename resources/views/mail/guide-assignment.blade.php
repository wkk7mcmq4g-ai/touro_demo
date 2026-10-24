{{-- Tour guide assignment (PHPRunner Send_Assistant_Email), with the order as PDF attached. --}}
@php $day = $job->TransferDate?->copy()->locale('el'); @endphp
<x-mail.layout
    :title="$day?->translatedFormat('j F Y') ?? 'Εντολή ανάθεσης'"
    :kicker="$day?->translatedFormat('l')"
    eyebrow="Εντολή ανάθεσης ξενάγησης"
    :pills="array_values(array_filter([$job->TransferTime ? 'Παραλαβή '.$job->TransferTime : null, $job->TransferType, filled($job->Pax) ? $job->Pax.' άτομα' : null]))"
    :preheader="'Ανάθεση ξενάγησης '.($day?->translatedFormat('l j/n') ?? '').' · '.$job->TransferTime.' · '.$job->From"
>
    <p class="mx-t" style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0f172a">
        Σας αναθέτουμε την παρακάτω ξενάγηση. Η εντολή ανάθεσης επισυνάπτεται σε PDF.
    </p>
    @include('mail.partials.job', ['job' => $job, 'showNotes' => false, 'showDriver' => true, 'withPassengers' => false])
</x-mail.layout>
