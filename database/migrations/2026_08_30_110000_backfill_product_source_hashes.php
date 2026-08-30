<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Product::query()
            ->whereNotNull('template_id')
            ->whereNull('source_hash')
            ->with('template')
            ->each(function (Product $product): void {
                if (! $product->template || $product->templateDifferences() !== []) {
                    return;
                }

                $product->forceFill(['source_hash' => $product->template->source_hash])->saveQuietly();
            });
    }

    public function down(): void
    {
        // Backfilled values are recomputable; nothing to reverse.
    }
};
