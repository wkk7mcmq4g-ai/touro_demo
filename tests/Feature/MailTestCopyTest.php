<?php

namespace Tests\Feature;

use App\Mail\OlpEntryRequest;
use App\Models\VTransfer;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailTestCopyTest extends TestCase
{
    public function test_always_to_sends_only_to_the_tester_with_the_real_recipients_on_top(): void
    {
        config(['mail.default' => 'array', 'mail.to' => ['address' => 'tester@example.test', 'name' => 'Test copy']]);
        Mail::purge('array');
        $sent = null;
        Event::listen(MessageSending::class, function (MessageSending $e) use (&$sent): void {
            $sent = $e->message;
        });

        $job = (new VTransfer)->forceFill(['TransferID' => 1, 'TransferDate' => '2026-10-25', 'TransferTime' => '13:20', 'From' => 'Piraeus Port', 'To' => 'Athens Airport', 'Flight_Ship_Num_From' => 'Celebrity Edge']);
        // No PDF in this test: only the recipients and the strip matter.
        $mail = new class($job) extends OlpEntryRequest
        {
            public function attachments(): array
            {
                return [];
            }
        };
        Mail::mailer('array')->to('port@example.test')->cc('copy@example.test')->bcc('office@example.test')->send($mail);

        $this->assertSame(['tester@example.test'], array_map(fn ($a) => $a->getAddress(), $sent->getTo()));
        $this->assertSame([], $sent->getCc());
        $this->assertSame([], $sent->getBcc());
        $this->assertStringContainsString('port@example.test', $sent->getHtmlBody());
        $this->assertStringContainsString('office@example.test', $sent->getHtmlBody());
        $this->assertStringContainsString('Δοκιμαστικό αντίγραφο', $sent->getHtmlBody());
    }
}
