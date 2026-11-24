<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Gym equipment / supplement inventory. Every product now belongs to a
     * Brand and Category record. Two discontinued items are seeded as inactive
     * to demonstrate that "delete" only marks a product unavailable.
     */
    public function run(): void
    {
        $brandIds = Brand::pluck('id', 'name');
        $categoryIds = Category::pluck('id', 'name');
        $supplierIds = Supplier::pluck('id', 'company_name');

        $products = [
            ['name' => 'Adjustable Dumbbell Set 20kg', 'sku' => 'JT-EQP-001', 'brand' => 'Ironbulk', 'category' => 'Equipment', 'quantity' => 12, 'reorder_level' => 5, 'unit_price' => 12500, 'supplier' => 'Ironbulk Supply', 'description' => 'Space-saving adjustable dumbbells for strength training.'],
            ['name' => 'Olympic Barbell 20kg', 'sku' => 'JT-EQP-002', 'brand' => 'Ironbulk', 'category' => 'Equipment', 'quantity' => 3, 'reorder_level' => 5, 'unit_price' => 8900, 'supplier' => 'Ironbulk Supply', 'description' => 'Standard 20kg Olympic barbell for free weight workouts.'],
            ['name' => 'Commercial Treadmill X9', 'sku' => 'JT-EQP-003', 'brand' => 'Stride Mechanics', 'category' => 'Equipment', 'quantity' => 0, 'reorder_level' => 2, 'unit_price' => 45000, 'supplier' => 'Stride Mechanics', 'description' => 'Heavy-duty treadmill with incline and heart-rate sensors.'],
            ['name' => 'Foldable Weight Bench', 'sku' => 'JT-EQP-004', 'brand' => 'Ironbulk', 'category' => 'Equipment', 'quantity' => 8, 'reorder_level' => 4, 'unit_price' => 6200, 'supplier' => 'Ironbulk Supply', 'description' => 'Adjustable incline bench that folds for easy storage.'],
            ['name' => 'Resistance Band Kit', 'sku' => 'JT-EQP-005', 'brand' => 'FlexFit', 'category' => 'Equipment', 'quantity' => 25, 'reorder_level' => 10, 'unit_price' => 950, 'supplier' => 'FlexFit Distributors', 'description' => 'Five-band set with different resistance levels.'],
            ['name' => 'Kettlebell 16kg', 'sku' => 'JT-EQP-006', 'brand' => 'Ironbulk', 'category' => 'Equipment', 'quantity' => 4, 'reorder_level' => 6, 'unit_price' => 2450, 'supplier' => 'Ironbulk Supply', 'description' => 'Cast iron kettlebell for swings and goblet squats.'],
            ['name' => 'Whey Protein 1kg', 'sku' => 'JT-SUP-001', 'brand' => 'NutriCore', 'category' => 'Supplements', 'quantity' => 40, 'reorder_level' => 15, 'unit_price' => 1895, 'supplier' => 'NutriCore Pharma', 'description' => 'Whey protein concentrate, chocolate flavor.'],
            ['name' => 'Creatine Monohydrate 300g', 'sku' => 'JT-SUP-002', 'brand' => 'NutriCore', 'category' => 'Supplements', 'quantity' => 2, 'reorder_level' => 10, 'unit_price' => 1195, 'supplier' => 'NutriCore Pharma', 'description' => 'Micronized creatine for strength and performance.'],
            ['name' => 'Pre-Workout 250g', 'sku' => 'JT-SUP-003', 'brand' => 'NutriCore', 'category' => 'Supplements', 'quantity' => 18, 'reorder_level' => 8, 'unit_price' => 1395, 'supplier' => 'NutriCore Pharma', 'description' => 'Caffeine and citrulline blend for workout energy.'],
            ['name' => 'Dri-Fit Training Shirt', 'sku' => 'JT-APP-001', 'brand' => 'ActiveWear', 'category' => 'Apparel', 'quantity' => 60, 'reorder_level' => 20, 'unit_price' => 750, 'supplier' => 'ActiveWear Co.', 'description' => 'Breathable short-sleeve shirt for gym sessions.'],
            ['name' => 'Compression Leggings', 'sku' => 'JT-APP-002', 'brand' => 'ActiveWear', 'category' => 'Apparel', 'quantity' => 7, 'reorder_level' => 15, 'unit_price' => 1250, 'supplier' => 'ActiveWear Co.', 'description' => 'High-waisted compression tights with side pocket.'],
            ['name' => 'Gym Training Gloves', 'sku' => 'JT-APP-003', 'brand' => 'ProGrip', 'category' => 'Apparel', 'quantity' => 30, 'reorder_level' => 12, 'unit_price' => 550, 'supplier' => 'ActiveWear Co.', 'description' => 'Padded palm protection for lifting.'],
            ['name' => 'Shaker Bottle 700ml', 'sku' => 'JT-ACC-001', 'brand' => 'HydroGear', 'category' => 'Accessories', 'quantity' => 80, 'reorder_level' => 25, 'unit_price' => 350, 'supplier' => 'HydroGear', 'description' => 'Leak-proof shaker with mixing ball.'],
            ['name' => 'Speed Jump Rope', 'sku' => 'JT-ACC-002', 'brand' => 'HydroGear', 'category' => 'Accessories', 'quantity' => 14, 'reorder_level' => 10, 'unit_price' => 490, 'supplier' => 'HydroGear', 'description' => 'Lightweight rope for cardio and HIIT training.'],
            ['name' => 'Microfiber Gym Towel', 'sku' => 'JT-ACC-003', 'brand' => 'HydroGear', 'category' => 'Accessories', 'quantity' => 3, 'reorder_level' => 12, 'unit_price' => 250, 'supplier' => 'HydroGear', 'description' => 'Quick-dry towel for sweat and equipment.'],
            ['name' => 'Energy Bar Box (12 pcs)', 'sku' => 'JT-NUT-001', 'brand' => 'NutriCore', 'category' => 'Nutrition', 'quantity' => 22, 'reorder_level' => 10, 'unit_price' => 890, 'supplier' => 'NutriCore Pharma', 'description' => 'Box of 12 protein energy bars.'],
            ['name' => 'Electrolyte Powder 30 Sticks', 'sku' => 'JT-NUT-002', 'brand' => 'NutriCore', 'category' => 'Nutrition', 'quantity' => 9, 'reorder_level' => 12, 'unit_price' => 1050, 'supplier' => 'NutriCore Pharma', 'description' => 'Hydration sticks for long training sessions.'],
            ['name' => 'Foam Roller 45cm', 'sku' => 'JT-REC-001', 'brand' => 'FlexFit', 'category' => 'Recovery', 'quantity' => 6, 'reorder_level' => 8, 'unit_price' => 1150, 'supplier' => 'FlexFit Distributors', 'description' => 'Medium-density roller for myofascial release.'],
            ['name' => 'Compact Massage Gun', 'sku' => 'JT-REC-002', 'brand' => 'FlexFit', 'category' => 'Recovery', 'quantity' => 1, 'reorder_level' => 3, 'unit_price' => 3295, 'supplier' => 'FlexFit Distributors', 'description' => 'Handheld percussion massager for post-workout recovery.'],

            // Discontinued items: inactive, but still retained in the database.
            ['name' => 'Legacy Treadmill X5', 'sku' => 'JT-EQP-000', 'brand' => 'Stride Mechanics', 'category' => 'Equipment', 'quantity' => 0, 'reorder_level' => 2, 'unit_price' => 28000, 'supplier' => 'Stride Mechanics', 'description' => 'Discontinued model kept for historical records.', 'is_active' => false],
            ['name' => 'Discontinued Whey 500g', 'sku' => 'JT-SUP-000', 'brand' => 'NutriCore', 'category' => 'Supplements', 'quantity' => 2, 'reorder_level' => 5, 'unit_price' => 695, 'supplier' => 'NutriCore Pharma', 'description' => 'Old packaging, no longer sold.', 'is_active' => false],
        ];

        foreach ($products as $product) {
            $brandName = $product['brand'];
            $categoryName = $product['category'];
            unset($product['brand'], $product['category']);

            $product['brand_id'] = $brandIds[$brandName];
            $product['category_id'] = $categoryIds[$categoryName];
            $product['supplier_id'] = $supplierIds[$product['supplier']] ?? null;
            $product['is_active'] ??= true;

            Product::updateOrCreate(['sku' => $product['sku']], $product);
        }
    }
}
