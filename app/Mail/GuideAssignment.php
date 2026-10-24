<?php

namespace App\Mail;

use App\Models\VTransfer;
use App\Services\Documents\Vouchers;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** PHPRunner "Αποστολή Email" on the tour guide voucher (Send_Assistant_Email). */
class GuideAssignment extends Mailable
{
    public function __construct(public VTransfer $job) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'MyAthensTransfers'),
            replyTo: [config('dispatch.reply_to')],
            subject: 'Εντολή Ανάθεσης Ξενάγησης'.($this->job->TransferDate ? ' · '.$this->job->TransferDate->format('d/m/Y') : ''),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.guide-assignment', text: 'mail.guide-assignment-text');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => app(Vouchers::class)->pdf('Voucher_assistant', $this->job->getAttributes()), 'ΕΝΤΟΛΗ.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
