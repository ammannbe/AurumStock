<?php

namespace App\Mcp\Support;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

class SupplierResolver
{
    /**
     * Finds the user's supplier by name (case-insensitive) or creates it.
     *
     * @param  array<string, mixed>  $attributes  Only used when creating.
     * @return array{0: Supplier, 1: bool} The supplier and whether it was created.
     */
    public static function findOrCreate(User $user, string $name, array $attributes = []): array
    {
        $name = trim($name);

        $existing = self::find($user, $name);

        if ($existing) {
            return [$existing, false];
        }

        try {
            $supplier = Supplier::query()->create([
                ...array_filter($attributes, fn (mixed $value): bool => $value !== null),
                'user_id' => $user->id,
                'name' => $name,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Created concurrently in the meantime.
            $supplier = self::find($user, $name);

            if (! $supplier) {
                throw new \RuntimeException("Supplier {$name} could not be created.");
            }

            return [$supplier, false];
        }

        return [$supplier, true];
    }

    public static function find(User $user, string $name): ?Supplier
    {
        return Supplier::query()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->first();
    }
}
