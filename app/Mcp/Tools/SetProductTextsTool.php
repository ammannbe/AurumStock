<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('set-product-texts')]
#[Description('Set the inscriptions of one of the user\'s products: text_front (image side) and/or text_back (value side), e.g. "BRITANNIA 2024 · 1/4 oz · 999 FINE SILVER". Separate inscription parts with " · ". Fills empty texts; replace=true replaces existing ones (needs the replace permission).')]
class SetProductTextsTool extends AurumTool
{
    /** @var array<string, string> */
    public const FIELDS = [
        'text_front' => 'front (image side)',
        'text_back' => 'back (value side)',
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
            'text_front' => ['nullable', 'string', 'max:255', 'required_without:text_back'],
            'text_back' => ['nullable', 'string', 'max:255'],
            'replace' => ['nullable', 'boolean'],
        ], [
            'text_front.required_without' => 'Pass text_front and/or text_back.',
        ]);

        $product = Product::query()->find($data['product_id']);

        if (! $product || Gate::forUser($user)->denies('update', $product)) {
            throw ValidationException::withMessages(['product_id' => "Product #{$data['product_id']} not found."]);
        }

        $changes = [];
        $unchanged = [];

        foreach (self::FIELDS as $field => $label) {
            $value = trim((string) ($data[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            $current = trim((string) $product->getAttribute($field));

            if ($current === $value) {
                $unchanged[] = $field;

                continue;
            }

            if ($current !== '') {
                if (! ($data['replace'] ?? false)) {
                    throw ValidationException::withMessages([
                        $field => "Product #{$product->id} already has a {$label} text (\"{$current}\"); pass replace=true to replace it.",
                    ]);
                }

                $this->ensureMayReplace($request, $field, "Product #{$product->id} already has a {$label} text.");
            }

            $changes[$field] = $value;
        }

        if ($changes !== []) {
            $product->forceFill($changes)->save();
        }

        $summary = $changes === []
            ? "Nothing changed: product {$product->name} (#{$product->id}) already has these texts."
            : 'Set '.implode(' and ', array_map(fn (string $field): string => self::FIELDS[$field], array_keys($changes)))." text of product {$product->name} (#{$product->id}).";

        return $this->respond($summary, [
            'product_id' => $product->id,
            'set' => array_keys($changes),
            'unchanged' => $unchanged,
            'text_front' => $product->text_front,
            'text_back' => $product->text_back,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->required(),
            'text_front' => $schema->string()->description('Inscription of the image side (obverse), max 255 characters.'),
            'text_back' => $schema->string()->description('Inscription of the value side (reverse), max 255 characters.'),
            'replace' => $schema->boolean()->description('Replace existing texts (needs the replace permission). Default false.'),
        ];
    }
}
