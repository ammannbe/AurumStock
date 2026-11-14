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
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add-product-media')]
#[Description('Append an image or video to the "Additional media" of one of the user\'s products (images: jpg, png, webp, gif, avif, heic; videos: mp4, webm, ogv, mov). Pass media_url (public https URL, downloaded by the server, max 40 MB; preferred) or content_base64 (max 10 MB). Existing media are kept; a file that is already attached is skipped.')]
class AddProductMediaTool extends AurumTool
{
    public const MAX_URL_BYTES = 40 * 1024 * 1024;

    public const MAX_MEDIA = 30;

    protected function permission(): McpPermission
    {
        return McpPermission::Images;
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'label' => ['nullable', 'string', 'max:255'],
            ...MediaInput::rules('media_url'),
        ], MediaInput::messages('media_url'));

        $product = Product::query()->find($data['product_id']);

        if (! $product || Gate::forUser($user)->denies('update', $product)) {
            throw ValidationException::withMessages(['product_id' => "Product #{$data['product_id']} not found."]);
        }

        $assets = array_values(array_filter(
            is_array($product->assets) ? $product->assets : [],
            fn (mixed $asset): bool => is_array($asset) && filled($asset['path'] ?? null),
        ));

        if (count($assets) >= self::MAX_MEDIA) {
            throw ValidationException::withMessages(['product_id' => "Product #{$product->id} already has ".self::MAX_MEDIA.' additional media.']);
        }

        $file = MediaInput::load(
            $data,
            'media_url',
            [...MediaInput::IMAGE_EXTENSIONS, ...MediaInput::VIDEO_EXTENSIONS],
            self::MAX_URL_BYTES,
            'image or video',
        );

        $disk = Storage::disk(ProductMediaFiles::DISK);
        $hash = sha1($file['bytes']);
        $media = fn (array $list): array => array_map(fn (array $asset): array => [
            'label' => $asset['label'] ?? null,
            'filename' => basename((string) $asset['path']),
        ], $list);

        // Agents retry; never attach the same file twice (also covers the front and back image).
        foreach ([$product->front_image_path, $product->back_image_path, ...array_column($assets, 'path')] as $existing) {
            if (is_string($existing) && $existing !== '' && $disk->exists($existing) && sha1((string) $disk->get($existing)) === $hash) {
                return $this->respond(
                    "Skipped: this file is already attached to product {$product->name} (#{$product->id}).",
                    ['product_id' => $product->id, 'skipped' => true, 'media' => $media($assets)],
                );
            }
        }

        $path = 'products/media/'.Str::random(40).'.'.$file['extension'];

        $disk->put($path, $file['bytes']);

        $assets[] = [
            'path' => $path,
            'label' => filled($data['label'] ?? null) ? $data['label'] : null,
        ];

        $product->forceFill(['assets' => $assets])->save();

        $kind = str_starts_with($file['mime'], 'video/') ? 'video' : 'image';

        return $this->respond(
            "Added a {$kind} to the additional media of product {$product->name} (#{$product->id}); it now has ".count($assets).' additional media.',
            [
                'product_id' => $product->id,
                'skipped' => false,
                'added' => ['kind' => $kind, 'filename' => basename($path), 'mime_type' => $file['mime'], 'size' => strlen($file['bytes'])],
                'media' => $media($assets),
            ],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->required(),
            'media_url' => $schema->string()->description('Public https URL of the image or video; the server downloads it (max 40 MB, up to 3 redirects). Preferred over content_base64.'),
            'content_base64' => $schema->string()->description('Base64-encoded file (a data URL is accepted too, max 10 MB); only when there is no URL.'),
            'filename' => $schema->string()->description('File name incl. extension, e.g. "britannia-edge.jpg"; required with content_base64.'),
            'label' => $schema->string()->description('Optional caption shown in the app, e.g. "Rand", "Verpackung", "Video".'),
        ];
    }
}
