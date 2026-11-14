<?php

namespace App\Mcp\Tools;

use App\Mcp\McpPermission;
use App\Mcp\Support\Present;
use App\Models\Order;
use App\Support\MediaTypes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('attach-order-receipt')]
#[Description('Attach a receipt / invoice file (PDF or image: pdf, jpg, jpeg, png, webp, heic; max 10 MB) to one of the user\'s orders. The file content is passed base64-encoded.')]
class AttachOrderReceiptTool extends AurumTool
{
    protected function permission(): McpPermission
    {
        return McpPermission::Receipts;
    }

    public const MAX_BYTES = 10 * 1024 * 1024;

    /** @var array<int, string> */
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'];

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user($request);

        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'filename' => ['required', 'string', 'max:255'],
            'content_base64' => ['required', 'string', 'max:'.((int) ceil(self::MAX_BYTES / 3) * 4 + 100)],
            'label' => ['nullable', 'string', 'max:255'],
        ], [
            'content_base64.max' => 'The file must not be larger than 10 MB.',
        ]);

        $order = Order::query()->find($data['order_id']);

        if (! $order || Gate::forUser($user)->denies('update', $order)) {
            throw ValidationException::withMessages(['order_id' => "Order #{$data['order_id']} not found."]);
        }

        $filename = basename(str_replace('\\', '/', $data['filename']));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'filename' => 'Only these file types are allowed: '.implode(', ', self::EXTENSIONS).'.',
            ]);
        }

        $bytes = $this->decode($data['content_base64']);

        if (strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['content_base64' => 'The file must not be larger than 10 MB.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        if (! in_array($mime, [...MediaTypes::forReceipts(), 'image/heif'], true)) {
            throw ValidationException::withMessages([
                'content_base64' => "The file content ({$mime}) is not a PDF or a supported image.",
            ]);
        }

        $path = Order::RECEIPT_DIRECTORY.'/'.Str::random(40).'.'.$extension;

        Storage::disk(Order::RECEIPT_DISK)->put($path, $bytes);

        $receipts = $order->receipts ?? [];
        $receipts[] = [
            'path' => $path,
            'label' => filled($data['label'] ?? null) ? $data['label'] : $filename,
        ];

        $order->receipts = $receipts;
        $order->save();

        return $this->respond(
            "Attached {$filename} to order {$order->order_number} (#{$order->id}). The order now has ".count($order->receiptEntries()).' receipt(s).',
            [
                'order_id' => $order->id,
                'receipt' => ['label' => end($receipts)['label'], 'filename' => basename($path), 'mime_type' => $mime, 'size' => strlen($bytes)],
                'receipts' => Present::receipts($order),
            ],
        );
    }

    protected function decode(string $content): string
    {
        // Accept data URLs ("data:application/pdf;base64,....") as well.
        if (str_starts_with($content, 'data:') && str_contains($content, ',')) {
            $content = substr($content, strpos($content, ',') + 1);
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', $content) ?? '', true);

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages(['content_base64' => 'The file content is not valid base64.']);
        }

        return $bytes;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_id' => $schema->integer()->required(),
            'filename' => $schema->string()->description('Original file name incl. extension, e.g. "rechnung-25822.pdf".')->required(),
            'content_base64' => $schema->string()->description('Base64-encoded file content (a data URL is accepted too).')->required(),
            'label' => $schema->string()->description('Optional label shown in the app, e.g. "Rechnung". Defaults to the file name.'),
        ];
    }
}
