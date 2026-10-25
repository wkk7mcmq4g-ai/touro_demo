<?php

namespace App\Support;

/**
 * Small helpers over config/business.php for the places that print the business's details.
 */
class Business
{
    /** E-mail signature of the operations desk (OLP request): name, title, business, website, phone. */
    public static function signatureHtml(): string
    {
        $ops = config('business.operations');

        return '<p><strong>'.e($ops['name']).'</strong><br>'.e($ops['title']).'</p>'
            .'<p>'.e(config('business.trade_name')).'<br>'.e(config('business.tagline'))
            .'<br>visit us: '.e(config('business.website')).'<br>mob: '.e($ops['phone']).'</p>';
    }

    /** The same signature as plain text lines. */
    public static function signatureText(): string
    {
        return trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], "\n", self::signatureHtml()))));
    }

    /** "<legal name> · ΜΗ.Τ.Ε <number>": the client line of the daily dispatch. */
    public static function legalLine(): string
    {
        return config('business.legal_name').' · ΜΗ.Τ.Ε '.config('business.mhte');
    }

    /** A phone number as a tel: link target (digits and +). */
    public static function tel(?string $phone): string
    {
        return preg_replace('/[^\d+]/', '', (string) $phone) ?? '';
    }
}
