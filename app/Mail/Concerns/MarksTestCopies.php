<?php

namespace App\Mail\Concerns;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * With MAIL_ALWAYS_TO set (test copies), Laravel sends every e-mail only to that address and drops
 * Cc and Bcc. This puts a yellow strip at the top of the e-mail saying who it would have gone to,
 * so the test copy shows the recipients too. Without MAIL_ALWAYS_TO nothing changes.
 */
trait MarksTestCopies
{
    public function send($mailer)
    {
        if (filled(config('mail.to.address'))) {
            // Runs while the real recipients are still on the message, before Laravel replaces them.
            $this->withSymfonyMessage(function (Email $email): void {
                $list = fn (array $addresses): string => $addresses === [] ? '—' : implode(', ', array_map(fn (Address $a): string => $a->getAddress(), $addresses));
                [$to, $cc, $bcc] = [$list($email->getTo()), $list($email->getCc()), $list($email->getBcc())];

                $strip = '<div style="background:#fef08a;color:#713f12;border-bottom:2px solid #eab308;padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5">'
                    .'<strong>&#9888; Δοκιμαστικό αντίγραφο</strong> — θα πήγαινε σε: '
                    .'<strong>To:</strong> '.e($to).' &middot; <strong>Cc:</strong> '.e($cc).' &middot; <strong>Bcc:</strong> '.e($bcc).'</div>';

                if (is_string($html = $email->getHtmlBody())) {
                    $email->html(preg_match('/<body[^>]*>/i', $html) ? preg_replace('/(<body[^>]*>)/i', '$1'.$strip, $html, 1) : $strip.$html);
                }
                if (is_string($text = $email->getTextBody())) {
                    $email->text("[ΔΟΚΙΜΗ] Θα πήγαινε σε: To: {$to} | Cc: {$cc} | Bcc: {$bcc}\n\n".$text);
                }
            });
        }

        return parent::send($mailer);
    }
}
