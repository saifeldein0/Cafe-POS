<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Draws the user's initial as an inline SVG, so the panel never calls an outside avatar service
 * (the cafe's machine may be offline, and nothing should leave it).
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = trim(Filament::getNameForDefaultAvatar($record));
        $initial = mb_strtoupper(mb_substr(preg_replace('/^[^\p{L}\p{N}]+/u', '', $name) ?: '?', 0, 1));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#8f4a1d"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" fill="#fff" '
            .'font-family="Segoe UI, system-ui, sans-serif" font-size="30" font-weight="600">'
            .e($initial)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
