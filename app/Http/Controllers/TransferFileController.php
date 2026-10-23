<?php

namespace App\Http\Controllers;

use App\Models\MyatTransfersFile;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a transfer file out of its database blob.
 */
class TransferFileController extends Controller
{
    public function download(Request $request, int $id): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(filament()->getPanel('admin')->getLoginUrl());
        }

        abort_unless($user->canAccessPanel(filament()->getPanel('admin')), 403);
        abort_unless(Permissions::allows($user, 'myat_transfers_files', 'view'), 403);

        $file = MyatTransfersFile::query()->findOrFail($id);
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
