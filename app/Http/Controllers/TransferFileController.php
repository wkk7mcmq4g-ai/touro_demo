<?php

namespace App\Http\Controllers;

use App\Filament\Portal\Pages\MyAssignments;
use App\Models\MyatPartnerFile;
use App\Models\MyatTransfersFile;
use App\Models\MyatTransfersLoadFile;
use App\Models\VTransfer;
use App\Support\Permissions;
use App\Support\Portal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a file out of its database blob (transfer files, partner files).
 */
class TransferFileController extends Controller
{
    /** Blob file tables: route kind => [model, PHPRunner permission table]. */
    private const KINDS = [
        'transfers' => [MyatTransfersFile::class, 'myat_transfers_files'],
        'partners' => [MyatPartnerFile::class, 'myat_partner_files'],
        'loads' => [MyatTransfersLoadFile::class, 'myat_transfers_load_files'],
        // PHPRunner myat_coach_files: a transfer's files of the types marked "Partners see it",
        // for the partner the transfer is assigned to (Οι αναθέσεις μου).
        'assignments' => [MyatTransfersFile::class, 'myat_coach_files'],
    ];

    public function download(Request $request, int $id, string $kind = 'transfers'): Response
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        [$model, $table] = self::KINDS[$kind];

        $user = $request->user();

        if (! $user) {
            return redirect()->guest(filament()->getPanel('admin')->getLoginUrl());
        }

        $backOffice = $user->canAccessPanel(filament()->getPanel('admin'));
        abort_unless($backOffice || $user->canAccessPanel(filament()->getPanel('portal')), 403);
        abort_unless(Permissions::allows($user, $table, 'view'), 403);

        $file = $model::query()->findOrFail($id);

        // Portal users only get the files of their own transfers and booking requests;
        // drivers those of their jobs (and the customer's files the office shares with drivers).
        if ($kind === 'assignments') {
            $shared = (int) DB::table('optionvalues')->where('OptionID', $file->Type)->value('PartnerView') === 1;
            $assigned = $backOffice || MyAssignments::assigned($user->getKey())->where('t.TransferID', $file->TransferID)->exists();
            abort_unless($shared && $assigned, 404);
        } elseif (! $backOffice && $user->group()?->isDriver()) {
            $mine = VTransfer::query()->where('username', $user->username);
            $allowed = match ($kind) {
                'transfers' => $mine->where('TransferID', $file->TransferID)->exists(),
                'partners' => $mine->where('cID_Customer', $file->cid)->exists(),
                default => false,
            };
            abort_unless($allowed, 404);
        } elseif (! $backOffice) {
            $owner = match ($kind) {
                'transfers' => DB::table('transfers')->where('TransferID', $file->TransferID)->value('cID_Customer'),
                'loads' => DB::table('transfers_load')->where('LoadID', $file->TransferID)->value('cID_Customer'),
                default => null,
            };
            abort_unless($owner !== null && (int) $owner === Portal::customerId($user), 404);
        }
        $name = str_replace(['/', '\\'], '_', $file->Flnm ?: "file-{$file->ID}");
        $asciiName = preg_replace('/[^A-Za-z0-9._ ()-]/', '_', Str::ascii($name)) ?: "file-{$file->ID}";
        $content = (string) $file->getRawOriginal('Attachment');

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'application/octet-stream';

        // PDFs and plain images open in the browser; anything else (SVG included) downloads.
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);

        return response($content, 200, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $name,
                $asciiName,
            ),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
