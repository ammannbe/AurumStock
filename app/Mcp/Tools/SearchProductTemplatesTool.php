<?php

namespace App\Mcp\Tools;

use App\Enums\Material;
use App\Enums\ProductType;
use App\Enums\WeightUnit;
use App\Mcp\Support\Present;
use App\Mcp\Support\ProductInput;
use App\Models\ProductTemplate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-product-templates')]
#[Description('Search the global product template database (well-known coins and bars) by name, material, type, weight, year or manufacturer. Pass a found template_id to create-product / create-order items so all product details are copied from the template.')]
#[IsReadOnly]
class SearchProductTemplatesTool extends AurumTool
{
    public function handle(Request $request): ResponseFactory
    {
        $this->user($request);

        $input = ProductInput::normalizeEnums($request->all());

        $data = validator($input, [
            'search' => ['nullable', 'string', 'max:255'],
            'material' => ['nullable', Rule::enum(Material::class)],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['nullable', Rule::enum(WeightUnit::class)],
            'year' => ['nullable', 'integer'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ])->validate();

        $search = trim((string) ($data['search'] ?? ''));

        $templates = ProductTemplate::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                foreach (preg_split('/\s+/', $search) ?: [] as $term) {
                    $query->where(fn (Builder $q) => $q->where('name', 'like', '%'.$term.'%')
                        ->orWhere('manufacturer', 'like', '%'.$term.'%')
                        ->orWhere('text_front', 'like', '%'.$term.'%'));
                }
            })
            ->when(isset($data['material']), fn (Builder $q) => $q->where('material', $data['material']))
            ->when(isset($data['type']), fn (Builder $q) => $q->where('type', $data['type']))
            ->when(isset($data['weight']), fn (Builder $q) => $q->whereBetween('weight', [(float) $data['weight'] - 0.0001, (float) $data['weight'] + 0.0001]))
            ->when(isset($data['weight_unit']), fn (Builder $q) => $q->where('weight_unit', $data['weight_unit']))
            ->when(isset($data['year']), fn (Builder $q) => $q->where('year', $data['year']))
            ->when(isset($data['manufacturer']), fn (Builder $q) => $q->where('manufacturer', 'like', '%'.$data['manufacturer'].'%'))
            ->orderBy('name')
            ->limit((int) ($data['limit'] ?? 20))
            ->get();

        $items = $templates->map(fn (ProductTemplate $template): array => Present::template($template))->all();

        $summary = $templates->isEmpty()
            ? 'No product templates found.'
            : $templates->count().' template(s): '.$templates->map(fn (ProductTemplate $t): string => "#{$t->getKey()} {$t->displayName()}")->implode('; ');

        return $this->respond($summary, ['templates' => $items]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Words contained in the template name, manufacturer or front text, e.g. "Vreneli" or "Krugerrand".'),
            'material' => $schema->string()->enum(['gold', 'silver', 'platinum', 'palladium']),
            'type' => $schema->string()->enum(['ingot', 'coin']),
            'weight' => $schema->number()->description('Exact gross weight per item (in weight_unit).'),
            'weight_unit' => $schema->string()->enum(['gram', 'troy_ounce']),
            'year' => $schema->integer(),
            'manufacturer' => $schema->string(),
            'limit' => $schema->integer()->description('Maximum results (default 20, max 50).'),
        ];
    }
}
