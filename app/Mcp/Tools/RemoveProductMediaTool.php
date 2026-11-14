<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Models\Product;
use App\Support\ProductMediaFiles;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('remove-product-media')]
#[Description('Remove one entry from the "Additional media" of one of the user\'s products, identified by its filename (see "media" in search-products, get-order or add-product-media). The file is deleted unless another product or template still uses it.')]
class RemoveProductMediaTool extends AurumTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::ReplaceMedia;
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'filename' => ['required', 'string', 'max:255'],
        ]);

        $product = Product::query()->find($data['product_id']);

        if (! $product || Gate::forUser($user)->denies('update', $product)) {
            throw ValidationException::withMessages(['product_id' => "Product #{$data['product_id']} not found."]);
        }

        $assets = array_values(array_filter(
            is_array($product->assets) ? $product->assets : [],
            fn (mixed $asset): bool => is_array($asset) && filled($asset['path'] ?? null),
        ));

        $filename = basename(str_replace('\\', '/', $data['filename']));
        $index = array_search($filename, array_map(fn (array $asset): string => basename((string) $asset['path']), $assets), true);

        if ($index === false) {
            throw ValidationException::withMessages(['filename' => "Product #{$product->id} has no additional media named {$filename}."]);
        }

        $removed = $assets[$index];
        array_splice($assets, $index, 1);

        $product->forceFill(['assets' => $assets === [] ? null : $assets])->save();

        $deleted = ProductMediaFiles::deleteIfUnreferenced((string) $removed['path']);

        return $this->respond(
            "Removed {$filename} from the additional media of product {$product->name} (#{$product->id}); ".count($assets).' left.',
            [
                'product_id' => $product->id,
                'removed' => ['filename' => $filename, 'label' => $removed['label'] ?? null, 'file_deleted' => $deleted],
                'media' => array_map(fn (array $asset): array => [
                    'label' => $asset['label'] ?? null,
                    'filename' => basename((string) $asset['path']),
                ], $assets),
            ],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->required(),
            'filename' => $schema->string()->description('Filename of the media entry, e.g. "aB3…xyz.jpg".')->required(),
        ];
    }
}
