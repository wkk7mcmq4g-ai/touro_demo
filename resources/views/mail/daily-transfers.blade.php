{{-- A driver's or guide's transfers for the day (PHPRunner New_Button40 / New_Button47), as job cards. --}}
@php
    $day = $recipient->TransferDate->copy()->locale('el');
    $first = $jobs->pluck('TransferTime')->filter()->sort()->first();
    $pills = array_values(array_filter([
        $jobs->count() === 1 ? '1 δρομολόγιο' : $jobs->count().' δρομολόγια',
        $recipient->Type === 'Driver' ? $recipient->VehicleName : 'Συνοδός / ξεναγός',
        $first ? 'Πρώτη παραλαβή '.$first : null,
    ]));
@endphp
<x-mail.layout
    :title="$day->translatedFormat('j F Y')"
    :kicker="$day->translatedFormat('l')"
    eyebrow="Εντολή δρομολογίων"
    :pills="$pills"
    :preheader="implode(' · ', [$pills[0].' για '.$day->translatedFormat('l j/n'), ...array_slice($pills, 1)])"
>
    @foreach ($jobs as $job)
        @include('mail.partials.job', ['job' => $job, 'showNotes' => $recipient->Type === 'Driver', 'showDriver' => $recipient->Type !== 'Driver', 'withPassengers' => $withPassengers])
    @endforeach

    <x-slot:footer>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="mx-soft" style="background:#f8fafc;border-radius:10px">
            <tr><td class="mx-m" style="padding:12px 14px;font-size:12px;line-height:1.7;color:#475569">
                <strong class="mx-t" style="color:#0f172a">Εντολέας:</strong> My Athens Transfers IKE &middot; ΜΗ.Τ.Ε 0261E60000721601<br>
                <strong class="mx-t" style="color:#0f172a">Τρόπος πληρωμής:</strong> Πληρωμή με πίστωση βάσει ιδιωτικού συμφωνητικού
            </td></tr>
        </table>
    </x-slot:footer>
</x-mail.layout>
