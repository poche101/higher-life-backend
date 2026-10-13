<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    protected static function disk()
    {
        return Storage::disk(config('espee.media_disk'));
    }

    /** Public image URL (thumbnails, avatars). */
    public static function thumb(?string $path): ?string
    {
        if (! $path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return self::disk()->url($path);
    }

    /** Audio/video URL. Uses short-lived signed URLs on S3/R2/Spaces so files can't be hot-linked. */
    public static function media(?string $path): ?string
    {
        if (! $path) return null;
        if (str_starts_with($path, 'http')) return $path;
        try {
            if (config('filesystems.disks.'.config('espee.media_disk').'.driver') === 's3') {
                return self::disk()->temporaryUrl($path, now()->addHours(3));
            }
        } catch (\Throwable $e) {
            // fall through to the plain URL
        }
        return self::disk()->url($path);
    }
}
