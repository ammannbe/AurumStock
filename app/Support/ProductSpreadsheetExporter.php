<?php

namespace App\Support;

use App\Enums\Manufacturer;
use App\Enums\Material;
use App\Enums\Packaging;
use App\Enums\ProductType;
use App\Enums\Quality;
use App\Enums\WeightUnit;
use App\Models\Product;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\ODS\Writer as OdsWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductSpreadsheetExporter
{
    /**
     * @param  Collection<int, Product>  $products
     */
    public static function download(Collection $products, string $format): BinaryFileResponse
    {
        $writer = $format === 'ods' ? new OdsWriter : new CsvWriter;
        $path = tempnam(sys_get_temp_dir(), 'products-export-');

        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(static::headings()));

        foreach ($products as $product) {
            $writer->addRow(Row::fromValues(static::row($product)));
        }

        $writer->close();

        $filename = 'products-'.now()->format('Y-m-d').'.'.$format;

        return response()
            ->download($path, $filename, [
                'Content-Type' => $format === 'ods'
                    ? 'application/vnd.oasis.opendocument.spreadsheet'
                    : 'text/csv; charset=UTF-8',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * @return array<int, string>
     */
    protected static function headings(): array
    {
        return [
            __('Name'),
            __('Type'),
            __('Material'),
            __('Gross Weight'),
            __('Fine Weight'),
            __('Weight Unit'),
            __('Amount'),
            __('Total Purchase Price'),
            __('Purchase Currency'),
            __('Depository'),
            __('Purchase Date'),
            __('Tax Year'),
            __('Year'),
            __('Manufacturer'),
            __('Fineness'),
            __('Quality'),
            __('Packaging'),
            __('Country'),
            __('Notes'),
        ];
    }

    /**
     * @return array<int, string|int|float|null>
     */
    protected static function row(Product $product): array
    {
        $type = $product->getAttribute('type');
        $material = $product->getAttribute('material');
        $weightUnit = $product->getAttribute('weight_unit');
        $price = $product->getAttribute('price');
        $purchaseDate = $product->getAttribute('purchase_date');

        return [
            (string) $product->getAttribute('name'),
            $type instanceof ProductType ? $type->label() : null,
            $material instanceof Material ? $material->label() : null,
            (float) $product->getAttribute('weight'),
            round($product->fine_weight, 4),
            $weightUnit instanceof WeightUnit ? $weightUnit->label() : null,
            $product->getAttribute('amount'),
            $price !== null ? (float) $price : null,
            $product->getAttribute('price_currency'),
            $product->depository?->getAttribute('name'),
            $purchaseDate?->toDateString(),
            $product->getAttribute('tax_year'),
            $product->getAttribute('year'),
            $product->getAttribute('manufacturer') ? Manufacturer::from($product->getAttribute('manufacturer'))->label() : null,
            $product->getAttribute('fineness'),
            $product->getAttribute('quality') ? Quality::from($product->getAttribute('quality'))->label() : null,
            $product->getAttribute('packaging') ? Packaging::from($product->getAttribute('packaging'))->label() : null,
            $product->getAttribute('country_of_origin'),
            $product->getAttribute('notes'),
        ];
    }
}
