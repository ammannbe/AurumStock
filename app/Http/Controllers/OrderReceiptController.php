<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\MediaTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderReceiptController
{
    /**
     * Streams a receipt inline so the browser can preview it. The file is
     * resolved by index into the order's own list, never from the request.
     */
    public function __invoke(Request $request, Order $order, int $index): StreamedResponse
    {
        abort_unless($request->user()?->can('view', $order) ?? false, 403);

        $entries = $order->receiptEntries();

        abort_unless(array_key_exists($index, $entries), 404);

        $path = $entries[$index]['path'];
        $disk = Storage::disk(Order::RECEIPT_DISK);

        abort_unless($disk->exists($path), 404);

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        $isPreviewable = in_array($mime, MediaTypes::forReceipts(), true);

        return $disk->response($path, basename($path), [
            'Content-Type' => $isPreviewable ? $mime : 'application/octet-stream',
            // The sandbox directive would block Chrome's PDF viewer, so it is
            // only dropped for types we know the browser renders inertly.
            'Content-Security-Policy' => $isPreviewable
                ? "default-src 'none'; object-src 'self'; img-src 'self'; style-src 'unsafe-inline'"
                : "default-src 'none'; sandbox",
            'Content-Disposition' => $isPreviewable
                ? 'inline; filename="'.basename($path).'"'
                : 'attachment; filename="'.basename($path).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
