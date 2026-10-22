<?php

namespace App\Support;

use App\Models\Block;
use App\Models\StockLevel;

class BlockStockReport
{
    /**
     * Everything the print page, the PDF and the Excel file need for one block.
     */
    public static function for(Block $block): array
    {
        $block->loadMissing('godown');

        $rows = StockLevel::query()
            ->join('product_batches', 'product_batches.id', '=', 'stock_levels.batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->join('units', 'units.id', '=', 'products.base_unit_id')
            ->where('stock_levels.location_type', 'block')
            ->where('stock_levels.location_id', $block->id)
            ->where('stock_levels.quantity', '>', 0)
            ->groupBy('products.id', 'products.sku', 'products.name', 'units.abbreviation')
            ->orderBy('products.name')
            ->selectRaw(
                'products.id, products.sku, products.name, units.abbreviation as unit, '
                .'COUNT(stock_levels.id) as batches, SUM(stock_levels.quantity) as quantity, '
                .'MIN(product_batches.expiry_date) as next_expiry'
            )
            ->get();

        $total = (float) $rows->sum('quantity');
        $capacity = $block->capacity === null ? null : (float) $block->capacity;

        return [
            'block' => $block,
            'godown' => $block->godown,
            'rows' => $rows,
            'total' => $total,
            'capacity' => $capacity,
            'unit' => $block->capacity_unit,
            'available' => $capacity === null ? null : max(0, $capacity - $total),
            'generatedAt' => now(),
        ];
    }
}
