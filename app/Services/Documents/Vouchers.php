<?php

namespace App\Services\Documents;

use App\Support\Legacy\LegacyPage;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The PHPRunner voucher pages of v_transfers as PDFs:
 * - Voucher: lease contract (own IKE vehicles)
 * - Voucher1: transfer voucher (DXF vehicles)
 * - VoucherOLP: Piraeus port (OLP) entry request
 * - Voucher_assistant: tour guide assignment
 */
class Vouchers
{
    public const PAGES = [
        'Voucher' => 'Lease contract',
        'Voucher1' => 'Transfer voucher',
        'VoucherOLP' => 'OLP entry request',
        'Voucher_assistant' => 'Tour guide assignment',
    ];

    /** Which voucher a dispatch row prints (PHPRunner: IKE → Voucher, DXF → Voucher1, none for taxis/partners). */
    public static function forLicence(?string $licence): ?string
    {
        return match ($licence) {
            'IKE' => 'Voucher',
            'DXF' => 'Voucher1',
            default => null,
        };
    }

    /** @param  array<string, mixed>  $record  a v_transfers row */
    public function html(string $page, array $record): string
    {
        return view('documents.legacy-page', [
            'title' => self::PAGES[$page] ?? $page,
            'body' => LegacyPage::load('v_transfers', $page)->render($record),
        ])->render();
    }

    /** @param  array<string, mixed>  $record */
    public function pdf(string $page, array $record): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot(resource_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($page, $record), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public static function filename(string $page, array $record): string
    {
        return match ($page) {
            'VoucherOLP' => 'ΑΙΤΗΣΗ',
            'Voucher_assistant' => 'ΕΝΤΟΛΗ',
            default => 'Voucher',
        }.'-'.($record['TransferID'] ?? '').'.pdf';
    }
}
