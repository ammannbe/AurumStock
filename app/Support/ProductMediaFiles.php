<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductTemplate;
use Illuminate\Support\Facades\Storage;

/**
 * Tracks which files on the public disk are still referenced by a product or
 * template, so deleting one record never removes a file another one still uses.
 */
class ProductMediaFiles
{
    public const DISK = 'public';

    /** Directories owned by products and templates. */
    public const DIRECTORIES = ['products', 'product-templates'];

    /**
     * Paths referenced by a single record.
     *
     * @return array<int, string>
     */
    public static function pathsOf(Product|ProductTemplate $record): array
    {
        $paths = [$record->front_image_path, $record->back_image_path];

        foreach (is_array($record->assets) ? $record->assets : [] as $asset) {
            if (is_array($asset)) {
                $paths[] = $asset['path'] ?? null;
            }
        }

        return array_values(array_unique(array_filter(
            $paths,
            fn (mixed $path): bool => is_string($path) && $path !== '',
        )));
    }

    /**
     * Deletes the record's files, skipping any still referenced elsewhere.
     *
     * @return array<int, string> the paths that were actually deleted
     */
    public static function deleteUnreferenced(Product|ProductTemplate $record): array
    {
        $candidates = self::pathsOf($record);

        if ($candidates === []) {
            return [];
        }

        $referenced = self::referencedPaths($record);
        $deleted = [];

        foreach ($candidates as $path) {
            if (in_array($path, $referenced, true)) {
                continue;
            }

            Storage::disk(self::DISK)->delete($path);
            $deleted[] = $path;
        }

        return $deleted;
    }

    /**
     * All referenced paths, optionally ignoring the record being deleted.
     *
     * @return array<int, string>
     */
    public static function referencedPaths(Product|ProductTemplate|null $except = null): array
    {
        $paths = [];

        $products = Product::query()
            ->when(
                $except instanceof Product,
                fn ($query) => $query->whereKeyNot($except->getKey()),
            )
            ->get(['id', 'front_image_path', 'back_image_path', 'assets']);

        $templates = ProductTemplate::query()
            ->when(
                $except instanceof ProductTemplate,
                fn ($query) => $query->whereKeyNot($except->getKey()),
            )
            ->get(['id', 'front_image_path', 'back_image_path', 'assets']);

        foreach ($products->concat($templates) as $record) {
            /** @var Product|ProductTemplate $record */
            $paths = [...$paths, ...self::pathsOf($record)];
        }

        return array_values(array_unique($paths));
    }

    /**
     * Files present on disk under the product media directories.
     *
     * @return array<int, string>
     */
    public static function storedFiles(): array
    {
        $files = [];

        foreach (self::DIRECTORIES as $directory) {
            $files = [...$files, ...Storage::disk(self::DISK)->allFiles($directory)];
        }

        return $files;
    }

    /**
     * Stored files that no product or template references any more.
     *
     * @return array<int, string>
     */
    public static function orphans(): array
    {
        $referenced = self::referencedPaths();

        return array_values(array_filter(
            self::storedFiles(),
            fn (string $path): bool => ! in_array($path, $referenced, true),
        ));
    }
}
