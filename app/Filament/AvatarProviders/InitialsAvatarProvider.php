<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Initials avatar drawn locally, instead of Filament's default ui-avatars.com,
 * so staff names are never sent to an outside service.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = trim(Filament::getNameForDefaultAvatar($record));

        // First letter of the first two words that start with a letter (Greek included).
        preg_match_all('/(?<!\p{L})\p{L}/u', $name, $letters);
        $initials = mb_strtoupper(implode('', array_slice($letters[0], 0, 2))) ?: '?';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#fef3c7"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="Inter, Arial, sans-serif" '
            .'font-size="26" font-weight="600" fill="#92400e">'.e($initials).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
