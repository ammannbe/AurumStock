<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\Present;
use App\Models\Depository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-depositories')]
#[Description('List the user\'s depositories (storage locations). Every product needs a depository_id.')]
#[IsReadOnly]
class ListDepositoriesTool extends AurumTool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $depositories = Depository::query()
            ->where('user_id', $user->id)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        $items = $depositories->map(fn (Depository $depository): array => [
            ...Present::depository($depository),
            'products_count' => $depository->getAttribute('products_count'),
        ])->all();

        $summary = $depositories->isEmpty()
            ? 'No depositories found. The user has to create one in the app first.'
            : $depositories->count().' depositor(ies): '.$depositories->map(fn (Depository $d): string => "#{$d->getKey()} {$d->getAttribute('name')}")->implode(', ');

        return $this->respond($summary, ['depositories' => $items]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
