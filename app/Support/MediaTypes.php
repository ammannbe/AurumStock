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

    /** @var array<int, string> */
    public const DOCUMENT = [
        'application/pdf',
    ];

    /** @var array<int, string> */
    private const VIDEO_EXTENSIONS = [
        'mp4',
        'm4v',
        'webm',
        'ogv',
        'ogg',
        'mov',
        'qt',
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

    /**
     * Accepted MIME types for receipt uploads.
     *
     * @return array<int, string>
     */
    public static function forReceipts(): array
    {
        return [...self::DOCUMENT, ...self::IMAGE];
    }

    /**
     * Media kind derived from a file path; anything that is not a known video
     * extension is treated as an image.
     */
    public static function typeForPath(?string $path): string
    {
        $extension = strtolower(pathinfo($path ?? '', PATHINFO_EXTENSION));

        return in_array($extension, self::VIDEO_EXTENSIONS, true) ? 'video' : 'image';
    }
}
