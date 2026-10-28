<?php

namespace App\Mail;

use App\Mail\Concerns\MarksTestCopies;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * PHPRunner's users BeforeAdd: the new user gets their username, password and the login link.
 * The password is the one typed on the add page; it is never stored in clear text.
 */
class UserWelcome extends Mailable
{
    use MarksTestCopies;

    public function __construct(public User $user, public string $password) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: config('business.name').' · Στοιχεία σύνδεσης',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.user-welcome', text: 'mail.user-welcome-text', with: ['loginUrl' => url('/login')]);
    }
}
