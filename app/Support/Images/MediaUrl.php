<?php

namespace App\Support\Images;

use Illuminate\Support\Facades\Storage;

final class MediaUrl
{
    public static function for(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $diskName = (string) config('filesystems.media_disk');
        $disk = Storage::disk($diskName);

        if (config("filesystems.disks.{$diskName}.driver") === 's3') {
            return $disk->temporaryUrl($path, now()->addHour());
        }

        return $disk->url($path);
    }
}
