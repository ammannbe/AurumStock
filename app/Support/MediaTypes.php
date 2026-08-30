<?php

namespace App\Support;

class MediaTypes
{
    /** @var array<int, string> */
    public const IMAGE = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
        'image/heic',
    ];

    /** @var array<int, string> */
    public const VIDEO = [
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/quicktime',
    ];

    /**
     * Accepted MIME types for a media entry, falling back to both when the
     * type has not been chosen yet.
     *
     * @return array<int, string>
     */
    public static function for(?string $type): array
    {
        return match ($type) {
            'image' => self::IMAGE,
            'video' => self::VIDEO,
            default => [...self::IMAGE, ...self::VIDEO],
        };
    }
}
