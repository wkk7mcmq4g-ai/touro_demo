<?php

namespace App\Http\Controllers;

use App\Models\VTransfer;
use App\Services\Documents\Vouchers;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/** The voucher PDFs of the daily dispatch (PHPRunner v_transfers view pages). */
class VoucherController extends Controller
{
    public function show(Request $request, string $page, int $transfer, Vouchers $vouchers): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(filament()->getPanel('admin')->getLoginUrl());
        }

        $backOffice = $user->canAccessPanel(filament()->getPanel('admin'));
        abort_unless($backOffice || $user->canAccessPanel(filament()->getPanel('portal')), 403);
        abort_unless(Permissions::allows($user, 'v_transfers', 'view'), 403);
        abort_unless(array_key_exists($page, Vouchers::PAGES), 404);

        // Drivers and coach companies get the vouchers of their own jobs only.
        $job = VTransfer::query()->where('TransferID', $transfer)
            ->when(! $backOffice, fn ($query) => $query->where('username', $user->username))
            ->firstOrFail();
        $name = Vouchers::filename($page, $job->getAttributes());

        return response($vouchers->pdf($page, $job->getAttributes()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $name, 'voucher-'.$transfer.'.pdf'),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
