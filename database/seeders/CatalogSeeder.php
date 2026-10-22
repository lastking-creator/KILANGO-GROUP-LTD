<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            'Kilogram' => 'kg', 'Gram' => 'g', 'Litre' => 'L', 'Millilitre' => 'ml',
            'Piece' => 'pcs', 'Packet' => 'pkt', 'Bag' => 'bag', 'Sack' => 'sack',
            'Carton' => 'ctn', 'Crate' => 'crate', 'Bottle' => 'btl', 'Tin' => 'tin', 'Dozen' => 'doz',
        ];

        foreach ($units as $name => $abbreviation) {
            Unit::updateOrCreate(['name' => $name], ['abbreviation' => $abbreviation]);
        }

        $categories = [
            'Grains & cereals', 'Flour', 'Cooking oil & fats', 'Sugar & sweeteners', 'Beverages',
            'Dairy', 'Meat & fish', 'Fruits & vegetables', 'Spices & seasoning', 'Snacks',
            'Canned foods', 'Frozen foods', 'Other',
        ];

        foreach ($categories as $name) {
            Category::updateOrCreate(['name' => $name]);
        }
    }
}
