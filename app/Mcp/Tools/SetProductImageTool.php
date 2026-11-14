<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Models\Product;
use App\Support\MediaTypes;
use App\Support\ProductMediaFiles;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('set-product-image')]
#[Description('Add the front (image side) or back (value side) image to one of the user\'s products (jpg, jpeg, png, webp, gif, avif, heic; max 10 MB). The file content is passed base64-encoded. Only fills an empty side; an existing image is never replaced.')]
class SetProductImageTool extends AurumTool
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** @var array<int, string> */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'heic'];

    /** @var array<string, string> */
    public const SIDES = [
        'front' => 'front_image_path',
        'back' => 'back_image_path',
    ];

    protected function permission(): McpPermission
    {
        return McpPermission::Images;
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'side' => ['required', 'string', Rule::in(array_keys(self::SIDES))],
            'filename' => ['required', 'string', 'max:255'],
            'content_base64' => ['required', 'string', 'max:'.((int) ceil(self::MAX_BYTES / 3) * 4 + 100)],
        ], [
            'side.in' => 'side must be "front" (image side) or "back" (value side).',
            'content_base64.max' => 'The file must not be larger than 10 MB.',
        ]);

        $product = Product::query()->find($data['product_id']);

        if (! $product || Gate::forUser($user)->denies('update', $product)) {
            throw ValidationException::withMessages(['product_id' => "Product #{$data['product_id']} not found."]);
        }

        $field = self::SIDES[$data['side']];

        if (filled($product->getAttribute($field))) {
            throw ValidationException::withMessages([
                'side' => "Product #{$product->id} already has a {$data['side']} image; it can only be replaced in the app.",
            ]);
        }

        $filename = basename(str_replace('\\', '/', $data['filename']));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'filename' => 'Only these file types are allowed: '.implode(', ', self::EXTENSIONS).'.',
            ]);
        }

        $bytes = $this->decodeBase64($data['content_base64']);

        if (strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['content_base64' => 'The file must not be larger than 10 MB.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        if (! in_array($mime, [...MediaTypes::IMAGE, 'image/heif'], true)) {
            throw ValidationException::withMessages([
                'content_base64' => "The file content ({$mime}) is not a supported image.",
            ]);
        }

        $path = 'products/'.Str::random(40).'.'.$extension;

        Storage::disk(ProductMediaFiles::DISK)->put($path, $bytes);

        $product->forceFill([$field => $path])->save();

        $label = $data['side'] === 'front' ? 'front (image side)' : 'back (value side)';

        return $this->respond(
            "Added the {$label} image to product {$product->name} (#{$product->id}).",
            [
                'product_id' => $product->id,
                'side' => $data['side'],
                'image' => ['filename' => basename($path), 'mime_type' => $mime, 'size' => strlen($bytes)],
                'has_front_image' => filled($product->front_image_path),
                'has_back_image' => filled($product->back_image_path),
            ],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->required(),
            'side' => $schema->string()->enum(array_keys(self::SIDES))->description('"front" = image side (obverse), "back" = value side (reverse).')->required(),
            'filename' => $schema->string()->description('File name incl. extension, e.g. "britannia-front.jpg".')->required(),
            'content_base64' => $schema->string()->description('Base64-encoded image (a data URL is accepted too).')->required(),
        ];
    }
}
