<?php

namespace App\Mail;

use App\Models\DailyRecipient;
use App\Models\VTransfer;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** PHPRunner "Αποστολή Email" (New_Button40 / New_Button47): a driver's or guide's transfers for the day. */
class DailyTransfers extends Mailable
{
    /** @param  Collection<int, VTransfer>  $jobs */
    public function __construct(public DailyRecipient $recipient, public Collection $jobs, public bool $withPassengers = false) {}

    public function envelope(): Envelope
    {
        // PHPRunner sends a blind copy to the address(es) in optionvalues "EmailCC".
        $bcc = DB::table('optionvalues')->where('Option', 'EmailCC')->pluck('Value')->filter()->values()->all();

        return new Envelope(
            from: new Address(config('mail.from.address'), 'MyAthensTransfers'),
            bcc: $bcc,
            subject: $this->subjectLine(),
        );
    }

    /** e.g. "Δρομολόγια Κυριακή 25/10 · 2 transfers · VITO 2022". */
    public function subjectLine(): string
    {
        $day = $this->recipient->TransferDate->copy()->locale('el');
        $count = $this->jobs->count();

        return implode(' · ', array_filter([
            'Δρομολόγια '.$day->translatedFormat('l').' '.$day->format('d/m'),
            $count === 1 ? '1 transfer' : "{$count} transfers",
            $this->recipient->Type === 'Driver' ? trim((string) $this->recipient->VehicleName) : null,
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.daily-transfers', text: 'mail.daily-transfers-text');
    }
}
