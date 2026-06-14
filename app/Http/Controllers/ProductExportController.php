<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportProductsRequest;
use App\Models\Product;

class ProductExportController extends Controller
{
    public function export(ExportProductsRequest $request)
    {
        $query = Product::query()->where('user_id', $request->user()->id);

        // basic CSV export for now
        $products = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products.csv"',
        ];

        $columns = ['id', 'name', 'material', 'weight', 'amount', 'depository_id', 'price', 'price_currency', 'year', 'reference_number'];

        $callback = function () use ($products, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            foreach ($products as $p) {
                fputcsv($handle, array_map(fn ($c) => $p->$c, $columns));
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
