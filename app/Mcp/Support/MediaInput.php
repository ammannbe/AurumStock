<?php

namespace App\Mcp\Support;

use Illuminate\Validation\ValidationException;

/**
 * Loads a file passed to an MCP tool either as a public https URL (downloaded by the server) or
 * base64-encoded, and checks its real content type. The stored extension always follows the content.
 */
class MediaInput
{
    /** @var array<string, string> */
    public const IMAGE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
        'image/heic' => 'heic',
        'image/heif' => 'heic',
    ];

    /** @var array<string, string> */
    public const VIDEO_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
        'video/quicktime' => 'mov',
    ];

    public const BASE64_MAX_BYTES = 10 * 1024 * 1024;

    /**
     * Validation rules for the URL/base64 pair.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(string $urlField): array
    {
        return [
            $urlField => ['required_without:content_base64', 'prohibits:content_base64', 'nullable', 'string', 'max:2048'],
            'content_base64' => ['required_without:'.$urlField, 'nullable', 'string', 'max:'.((int) ceil(self::BASE64_MAX_BYTES / 3) * 4 + 100)],
            'filename' => ['required_with:content_base64', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $urlField): array
    {
        return [
            $urlField.'.required_without' => "Pass either {$urlField} or content_base64.",
            $urlField.'.prohibits' => "Pass either {$urlField} or content_base64, not both.",
            'content_base64.max' => 'The file must not be larger than 10 MB.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated tool input
     * @param  array<string, string>  $mimeExtensions  accepted MIME type => stored extension
     * @return array{bytes: string, mime: string, extension: string}
     */
    public static function load(array $data, string $urlField, array $mimeExtensions, int $maxUrlBytes, string $kind = 'image'): array
    {
        $fromUrl = filled($data[$urlField] ?? null);

        if ($fromUrl) {
            $bytes = app(RemoteFileFetcher::class)->fetch((string) $data[$urlField], $maxUrlBytes, $urlField);
        } else {
            $filename = basename(str_replace('\\', '/', (string) ($data['filename'] ?? '')));
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $allowed = self::knownExtensions($mimeExtensions);

            if (! in_array($extension, $allowed, true)) {
                throw ValidationException::withMessages([
                    'filename' => 'Only these file types are allowed: '.implode(', ', $allowed).'.',
                ]);
            }

            $bytes = self::decodeBase64((string) $data['content_base64']);

            if (strlen($bytes) > self::BASE64_MAX_BYTES) {
                throw ValidationException::withMessages(['content_base64' => 'The file must not be larger than 10 MB.']);
            }
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        if (! array_key_exists($mime, $mimeExtensions)) {
            throw ValidationException::withMessages([
                $fromUrl ? $urlField : 'content_base64' => "The file content ({$mime}) is not a supported {$kind}.",
            ]);
        }

        return ['bytes' => $bytes, 'mime' => $mime, 'extension' => $mimeExtensions[$mime]];
    }

    /**
     * Extensions that belong to the accepted MIME types (incl. common aliases).
     *
     * @param  array<string, string>  $mimeExtensions
     * @return list<string>
     */
    private static function knownExtensions(array $mimeExtensions): array
    {
        $extensions = array_values($mimeExtensions);

        if (in_array('jpg', $extensions, true)) {
            $extensions[] = 'jpeg';
        }

        if (in_array('mp4', $extensions, true)) {
            $extensions[] = 'm4v';
        }

        if (in_array('ogv', $extensions, true)) {
            $extensions[] = 'ogg';
        }

        if (in_array('mov', $extensions, true)) {
            $extensions[] = 'qt';
        }

        return array_values(array_unique($extensions));
    }

    /**
     * Decodes base64; data URLs ("data:image/png;base64,....") are accepted as well.
     */
    public static function decodeBase64(string $content): string
    {
        if (str_starts_with($content, 'data:') && str_contains($content, ',')) {
            $content = substr($content, strpos($content, ',') + 1);
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', $content) ?? '', true);

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages(['content_base64' => 'The file content is not valid base64.']);
        }

        return $bytes;
    }
}
