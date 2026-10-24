<?php

namespace App\Mail;

use App\Models\VTransfer;
use App\Services\Documents\Vouchers;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** PHPRunner "Αποστολή" on the OLP voucher (New_Button66): entry request to Piraeus port. */
class OlpEntryRequest extends Mailable
{
    public function __construct(public VTransfer $job, public ?string $replyToAddress = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'MyAthensTransfers'),
            replyTo: $this->replyToAddress ? [$this->replyToAddress] : [],
            subject: implode(' · ', array_filter(['Άδεια εισόδου παραλαβής & αποβίβασης', $this->job->TransferDate?->format('d/m/Y'), trim((string) $this->job->Flight_Ship_Num_From)])),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.olp-entry-request', text: 'mail.olp-entry-request-text');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => app(Vouchers::class)->pdf('VoucherOLP', $this->job->getAttributes()), 'ΑΙΤΗΣΗ.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
