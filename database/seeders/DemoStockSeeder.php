<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Category;
use App\Models\Godown;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockLevel;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * OPTIONAL sample data so you can see products inside a block.
 * Run with: php artisan db:seed --class=DemoStockSeeder
 * Remove it any time with: php artisan migrate:fresh --seed
 */
class DemoStockSeeder extends Seeder
{
    public function run(): void
    {
        $bag = Unit::updateOrCreate(['name' => 'Bag'], ['abbreviation' => 'bag']);
        $carton = Unit::updateOrCreate(['name' => 'Carton'], ['abbreviation' => 'ctn']);

        $grains = Category::updateOrCreate(['name' => 'Grains']);
        $drinks = Category::updateOrCreate(['name' => 'Beverages']);
        $dairy = Category::updateOrCreate(['name' => 'Dairy']);

        $products = [
            ['sku' => 'RICE-50', 'name' => 'Rice 50kg', 'category' => $grains, 'unit' => $bag, 'storage' => 'dry'],
            ['sku' => 'SUGAR-50', 'name' => 'Sugar 50kg', 'category' => $grains, 'unit' => $bag, 'storage' => 'dry'],
            ['sku' => 'SODA-24', 'name' => 'Soda (24 bottles)', 'category' => $drinks, 'unit' => $carton, 'storage' => 'dry'],
            ['sku' => 'MILK-12', 'name' => 'Fresh milk (12 packs)', 'category' => $dairy, 'unit' => $carton, 'storage' => 'cold'],
        ];

        $made = [];
        foreach ($products as $p) {
            $made[$p['sku']] = Product::updateOrCreate(
                ['sku' => $p['sku']],
                [
                    'name' => $p['name'],
                    'category_id' => $p['category']->id,
                    'base_unit_id' => $p['unit']->id,
                    'storage_type' => $p['storage'],
                ]
            );
        }

        $godown = Godown::where('code', 'G1')->first();
        if (! $godown) {
            return;
        }

        $blockA = Block::where('godown_id', $godown->id)->where('code', 'A')->first();
        $blockB = Block::where('godown_id', $godown->id)->where('code', 'B')->first();
        if (! $blockA || ! $blockB) {
            return;
        }

        $blockA->update(['capacity' => 500, 'capacity_unit' => 'units']);
        $blockB->update(['capacity' => 200, 'capacity_unit' => 'units', 'storage_type' => 'cold']);

        $stock = [
            // sku, batch, expiry (months from now), block, quantity
            ['RICE-50', 'RICE-001', 12, $blockA, 120],
            ['RICE-50', 'RICE-002', 18, $blockA, 80],
            ['SUGAR-50', 'SUG-001', 10, $blockA, 60],
            ['SODA-24', 'SODA-001', 6, $blockA, 150],
            ['MILK-12', 'MILK-001', 1, $blockB, 90],
        ];

        foreach ($stock as [$sku, $batchNo, $months, $block, $qty]) {
            $batch = ProductBatch::updateOrCreate(
                ['product_id' => $made[$sku]->id, 'batch_no' => $batchNo],
                ['received_date' => now()->toDateString(), 'expiry_date' => now()->addMonths($months)->toDateString()]
            );

            StockLevel::updateOrCreate(
                ['batch_id' => $batch->id, 'location_type' => 'block', 'location_id' => $block->id],
                ['quantity' => $qty]
            );
        }
    }
}
