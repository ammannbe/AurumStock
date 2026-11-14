<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddProductMediaTool;
use App\Mcp\Tools\AttachOrderReceiptTool;
use App\Mcp\Tools\CreateOrderTool;
use App\Mcp\Tools\CreateProductTool;
use App\Mcp\Tools\CreateSupplierTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\ListDepositoriesTool;
use App\Mcp\Tools\ListOrdersTool;
use App\Mcp\Tools\ListSuppliersTool;
use App\Mcp\Tools\SearchProductsTool;
use App\Mcp\Tools\SearchProductTemplatesTool;
use App\Mcp\Tools\SetProductImageTool;
use App\Mcp\Tools\SetProductTextsTool;
use Illuminate\Support\Facades\App;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

#[Name('AurumStock')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    AurumStock is a personal precious metals inventory (gold, silver, platinum, palladium coins and bars).
    This server imports purchases (orders) and holdings (products) for the authenticated user only.
    Users usually speak German (Swiss German spelling, "ss" instead of "ß"); answer them in German.

    Recommended import workflow for a receipt / invoice:
    1. list-orders (search by order number) to make sure the order was not imported yet.
    2. list-suppliers to find the supplier; otherwise pass supplier_name to create-order (found or created).
    3. list-depositories to pick the depository_id (ask the user if there is more than one and it is unclear).
    4. search-product-templates for every line item and use template_id when a template matches
       (material, type, weight and fineness must match). Without a template give name, material, type,
       weight and weight_unit explicitly.
    5. create-order with dry_run=true, show the preview to the user, then call it again without dry_run.
    6. attach-order-receipt to store the receipt file (base64) on the order.

    Product images: set-product-image adds the front (image side) or back (value side) image of a product.
    Prefer image_url (public https URL, downloaded by the server) over content_base64. It only fills an empty
    side (see has_front_image / has_back_image); existing images are kept.
    add-product-media appends further images or videos (e.g. edge, packaging, certificate, video) to the
    additional media, with an optional label; prefer media_url. set-product-texts sets the inscriptions
    (text_front = image side, text_back = value side, parts separated by " · ") when they are empty.
    Dealer pages (e.g. CIT) often list several images and videos: use front/back for the two coin sides
    and add-product-media for the rest. Never replace or remove existing media or texts.

    Conventions:
    - price is the total price of a line (all pieces), price_currency USD, CHF or EUR (default: user preference).
    - amount is the number of pieces; weight is the gross weight per piece.
    - Dates are YYYY-MM-DD; purchase_date defaults to the order date.
    - Never guess enum values; validation errors are returned as readable tool errors, fix the input and retry.
    MARKDOWN)]
class AurumServer extends Server
{
    protected array $tools = [
        ListSuppliersTool::class,
        CreateSupplierTool::class,
        ListDepositoriesTool::class,
        SearchProductTemplatesTool::class,
        SearchProductsTool::class,
        ListOrdersTool::class,
        GetOrderTool::class,
        CreateOrderTool::class,
        CreateProductTool::class,
        AttachOrderReceiptTool::class,
        SetProductImageTool::class,
        AddProductMediaTool::class,
        SetProductTextsTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];

    /**
     * Tool output and validation messages are always English for the AI client,
     * independent of the app (and fallback) locale, which has no German validation lines.
     *
     * @return iterable<JsonRpcResponse>|JsonRpcResponse
     */
    protected function runMethodHandle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
    {
        $locale = App::getLocale();
        App::setLocale('en');

        try {
            return parent::runMethodHandle($request, $context);
        } finally {
            App::setLocale($locale);
        }
    }
}
