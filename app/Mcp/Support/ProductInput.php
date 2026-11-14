<?php

namespace App\Mcp\Support;

use App\Enums\Currency;
use App\Enums\FaceValueCurrency;
use App\Enums\Fineness;
use App\Enums\Manufacturer;
use App\Enums\Material;
use App\Enums\Packaging;
use App\Enums\ProductType;
use App\Enums\Quality;
use App\Enums\WeightUnit;
use App\Filament\Resources\ProductResource;
use App\Models\Depository;
use App\Models\Product;
use App\Models\ProductTemplate;
use App\Models\User;
use App\Support\CurrencyConverter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Product fields accepted by the MCP import tools. The rules mirror the
 * Filament product form (App\Filament\Resources\ProductResource::form()).
 */
class ProductInput
{
    /**
     * Fields a caller may set explicitly; they override template values.
     *
     * @var array<int, string>
     */
    public const FIELDS = [
        'name', 'year', 'depository_id', 'material', 'type', 'manufacturer', 'country_of_origin',
        'size', 'amount', 'weight', 'weight_unit', 'fineness', 'quality', 'certificate',
        'reference_number', 'packaging', 'mintage', 'face_value', 'face_value_currency',
        'price', 'price_currency', 'purchase_date', 'tax_year', 'text_front', 'text_back', 'notes',
    ];

    /**
     * Int-backed enums that may also be given by case name (e.g. "gold", "troy_ounce").
     *
     * @var array<string, class-string<\BackedEnum>>
     */
    private const NAMED_ENUMS = [
        'material' => Material::class,
        'type' => ProductType::class,
        'weight_unit' => WeightUnit::class,
    ];

    /**
     * JSON schema properties shared by create-product and create-order items.
     *
     * @return array<string, Type>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'template_id' => $schema->integer()->description('Optional product template ID (see search-product-templates). Copies name, material, type, weight, fineness, images etc. from the template; explicitly given fields override the template values.'),
            'name' => $schema->string()->description('Product name. Required without template_id.'),
            'material' => $schema->string()->enum(self::enumNames(Material::class))->description('Required without template_id.'),
            'type' => $schema->string()->enum(self::enumNames(ProductType::class))->description('Required without template_id.'),
            'weight' => $schema->number()->description('Gross weight per item. Required without template_id.'),
            'weight_unit' => $schema->string()->enum(self::enumNames(WeightUnit::class))->description('Required without template_id.'),
            'amount' => $schema->integer()->description('Number of pieces (default 1).'),
            'price' => $schema->number()->description('Total purchase price for all pieces of this line.'),
            'price_currency' => $schema->string()->enum(array_column(Currency::cases(), 'value'))->description('Defaults to the user\'s preferred currency.'),
            'depository_id' => $schema->integer()->description('Depository ID (see list-depositories). Optional only when the user has exactly one depository.'),
            'purchase_date' => $schema->string()->format('date')->description('YYYY-MM-DD. Defaults to the order date when linked to an order.'),
            'tax_year' => $schema->integer(),
            'year' => $schema->integer()->description('Minting year.'),
            'fineness' => $schema->string()->enum(array_column(Fineness::cases(), 'value')),
            'manufacturer' => $schema->string()->enum(array_column(Manufacturer::cases(), 'value')),
            'country_of_origin' => $schema->string()->enum(array_keys(ProductResource::countryOfOriginOptions())),
            'quality' => $schema->string()->enum(array_column(Quality::cases(), 'value')),
            'packaging' => $schema->string()->enum(array_column(Packaging::cases(), 'value')),
            'certificate' => $schema->boolean(),
            'reference_number' => $schema->string(),
            'size' => $schema->string()->description('E.g. "38.6 mm".'),
            'mintage' => $schema->integer(),
            'face_value' => $schema->number(),
            'face_value_currency' => $schema->string()->enum(array_column(FaceValueCurrency::cases(), 'value')),
            'text_front' => $schema->string(),
            'text_back' => $schema->string(),
            'notes' => $schema->string(),
        ];
    }

    /**
     * Converts enum names to their values and fills in the only depository,
     * so the validation rules below can stay identical to the Filament form.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function normalize(array $item, User $user): array
    {
        $item = self::normalizeEnums($item);

        if (is_int($item['fineness'] ?? null) || is_float($item['fineness'] ?? null)) {
            $item['fineness'] = (string) $item['fineness'];
        }

        if (! array_key_exists('depository_id', $item) || $item['depository_id'] === null) {
            $depositoryIds = Depository::query()->where('user_id', $user->id)->limit(2)->pluck('id');

            if ($depositoryIds->count() === 1) {
                $item['depository_id'] = $depositoryIds->first();
            }
        }

        return $item;
    }

    /**
     * Accepts material / type / weight_unit by case name ("gold", "troy_ounce") as well as by value.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeEnums(array $input): array
    {
        foreach (self::NAMED_ENUMS as $field => $enum) {
            $value = $input[$field] ?? null;

            if (is_string($value) && ! is_numeric($value)) {
                $input[$field] = self::enumValueFromName($enum, $value) ?? $value;
            }
        }

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(User $user, string $prefix = ''): array
    {
        $templateField = $prefix.'template_id';

        return [
            $prefix.'template_id' => ['nullable', 'integer', Rule::exists('product_templates', 'id')],
            $prefix.'name' => ['required_without:'.$templateField, 'nullable', 'string', 'max:255'],
            $prefix.'year' => ['nullable', 'integer'],
            $prefix.'depository_id' => ['required', 'integer', Rule::exists('depositories', 'id')->where('user_id', $user->id)],
            $prefix.'material' => ['required_without:'.$templateField, 'nullable', Rule::enum(Material::class)],
            $prefix.'type' => ['required_without:'.$templateField, 'nullable', Rule::enum(ProductType::class)],
            $prefix.'manufacturer' => ['nullable', Rule::enum(Manufacturer::class)],
            $prefix.'country_of_origin' => ['nullable', Rule::in(array_keys(ProductResource::countryOfOriginOptions()))],
            $prefix.'size' => ['nullable', 'string', 'max:255'],
            $prefix.'amount' => ['nullable', 'integer', 'min:1'],
            $prefix.'weight' => ['required_without:'.$templateField, 'nullable', 'numeric', 'min:0'],
            $prefix.'weight_unit' => ['required_without:'.$templateField, 'nullable', Rule::enum(WeightUnit::class)],
            $prefix.'fineness' => ['nullable', Rule::enum(Fineness::class)],
            $prefix.'quality' => ['nullable', Rule::enum(Quality::class)],
            $prefix.'certificate' => ['nullable', 'boolean'],
            $prefix.'reference_number' => ['nullable', 'string', 'max:255'],
            $prefix.'packaging' => ['nullable', Rule::enum(Packaging::class)],
            $prefix.'mintage' => ['nullable', 'integer', 'min:0'],
            $prefix.'face_value' => ['nullable', 'numeric'],
            $prefix.'face_value_currency' => ['nullable', Rule::enum(FaceValueCurrency::class)],
            $prefix.'price' => ['nullable', 'numeric', 'min:0'],
            $prefix.'price_currency' => ['nullable', Rule::enum(Currency::class)],
            $prefix.'purchase_date' => ['nullable', 'date_format:Y-m-d'],
            $prefix.'tax_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            $prefix.'text_front' => ['nullable', 'string'],
            $prefix.'text_back' => ['nullable', 'string'],
            $prefix.'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Builds the attributes of a new product from validated input, starting
     * from the template like the Filament form does when one is selected.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function attributes(array $item, User $user, ?int $orderId = null, ?string $defaultPurchaseDate = null): array
    {
        $attributes = [];

        if (filled($item['template_id'] ?? null)) {
            $template = ProductTemplate::query()->findOrFail($item['template_id']);
            $attributes = Product::attributesFromTemplate($template);
        }

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $item) && $item[$field] !== null) {
                $attributes[$field] = $item[$field];
            }
        }

        $attributes['user_id'] = $user->id;
        $attributes['order_id'] = $orderId;
        $attributes['amount'] ??= 1;
        $attributes['certificate'] = (bool) ($attributes['certificate'] ?? false);
        $attributes['price_currency'] ??= CurrencyConverter::preferredCode();
        $attributes['purchase_date'] ??= $defaultPurchaseDate;

        return $attributes;
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     * @return array<int, string>
     */
    private static function enumNames(string $enum): array
    {
        return array_map(fn (\BackedEnum $case): string => Str::snake($case->name), $enum::cases());
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    private static function enumValueFromName(string $enum, string $name): int|string|null
    {
        $normalized = Str::lower(str_replace([' ', '-', '_'], '', $name));

        foreach ($enum::cases() as $case) {
            if (Str::lower($case->name) === $normalized) {
                return $case->value;
            }
        }

        return null;
    }
}
