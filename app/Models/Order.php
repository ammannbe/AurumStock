<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $supplier_id
 * @property string $order_number
 * @property Carbon|null $order_date
 * @property array<int, mixed>|null $receipts
 *
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order> factory(...$parameters)
 *
 * @property-read Supplier|null $supplier
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const RECEIPT_DIRECTORY = 'receipts';

    public const RECEIPT_DISK = 'local';

    protected $fillable = [
        'user_id',
        'supplier_id',
        'order_number',
        'order_date',
        'receipts',
    ];

    protected $casts = [
        'order_date' => 'date',
        'receipts' => 'array',
    ];

    /**
     * Receipt entries that actually carry a stored file path.
     *
     * @return array<int, array{path: string, label: string|null}>
     */
    public function receiptEntries(): array
    {
        $entries = [];

        foreach ($this->receipts ?? [] as $receipt) {
            if (! is_array($receipt)) {
                continue;
            }

            $path = $receipt['path'] ?? null;

            if (! is_string($path) || $path === '') {
                continue;
            }

            $label = $receipt['label'] ?? null;

            $entries[] = [
                'path' => $path,
                'label' => is_string($label) && $label !== '' ? $label : null,
            ];
        }

        return $entries;
    }

    /**
     * Receipt entries enriched with the URL and kind needed for previewing.
     *
     * @return array<int, array{path: string, label: string|null, url: string, is_pdf: bool, is_image: bool}>
     */
    public function previewableReceipts(): array
    {
        $previewable = [];

        foreach ($this->receiptEntries() as $index => $entry) {
            $extension = strtolower(pathinfo($entry['path'], PATHINFO_EXTENSION));

            $previewable[] = [
                ...$entry,
                'url' => route('orders.receipts.show', ['order' => $this->getKey(), 'index' => $index]),
                'is_pdf' => $extension === 'pdf',
                'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'heic'], true),
            ];
        }

        return $previewable;
    }

    /**
     * Remove files that are no longer referenced by the given receipt list.
     *
     * @param  array<int, mixed>|null  $newReceipts
     */
    public function deleteRemovedReceipts(?array $newReceipts): void
    {
        $kept = [];

        foreach ($newReceipts ?? [] as $receipt) {
            if (is_array($receipt) && isset($receipt['path']) && is_string($receipt['path'])) {
                $kept[] = $receipt['path'];
            }
        }

        foreach ($this->receiptEntries() as $entry) {
            if (! in_array($entry['path'], $kept, true)) {
                Storage::disk(self::RECEIPT_DISK)->delete($entry['path']);
            }
        }
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
