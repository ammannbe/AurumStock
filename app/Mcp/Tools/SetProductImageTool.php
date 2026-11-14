<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Mcp\Support\MediaInput;
use App\Models\Product;
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
#[Description('Add the front (image side) or back (value side) image to one of the user\'s products (jpg, jpeg, png, webp, gif, avif, heic; max 10 MB). Pass either image_url (public https URL, downloaded by the server; preferred) or content_base64. Only fills an empty side; an existing image is never replaced.')]
class SetProductImageTool extends AurumTool
{
    public const MAX_BYTES = 10 * 1024 * 1024;

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
            ...MediaInput::rules('image_url'),
        ], [
            ...MediaInput::messages('image_url'),
            'side.in' => 'side must be "front" (image side) or "back" (value side).',
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

        $file = MediaInput::load($data, 'image_url', MediaInput::IMAGE_EXTENSIONS, self::MAX_BYTES);
        $bytes = $file['bytes'];
        $mime = $file['mime'];
        $path = 'products/'.Str::random(40).'.'.$file['extension'];

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
            'image_url' => $schema->string()->description('Public https URL of the image; the server downloads it (max 10 MB, up to 3 redirects). Preferred over content_base64.'),
            'content_base64' => $schema->string()->description('Base64-encoded image (a data URL is accepted too); only when there is no URL.'),
            'filename' => $schema->string()->description('File name incl. extension, e.g. "britannia-front.jpg"; required with content_base64.'),
        ];
    }
}
