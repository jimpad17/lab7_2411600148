<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Suppliers mirror the legacy free-text names used by Lab 6. They are
     * linked back to products and to the categories they can supply.
     */
    public function run(): void
    {
        $suppliers = [
            'Ironbulk Supply' => [
                'contact_person' => 'Ramon Ironbulk',
                'email' => 'sales@ironbulk.example.com',
                'phone' => '02-8811-1200',
                'address' => '12 Quezon Ave, Quezon City',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 7,
                'minimum_order_amount' => 5000,
                'categories' => ['Equipment'],
            ],
            'Stride Mechanics' => [
                'contact_person' => 'Lina Stride',
                'email' => 'orders@stride.example.com',
                'phone' => '02-8822-3400',
                'address' => '88 Ortigas Ave, Pasig City',
                'payment_terms' => 'Net 45',
                'lead_time_days' => 14,
                'minimum_order_amount' => 15000,
                'categories' => ['Equipment'],
            ],
            'FlexFit Distributors' => [
                'contact_person' => 'Mika Flex',
                'email' => 'hello@flexfit.example.com',
                'phone' => '02-8890-7712',
                'address' => '5 Makati Ave, Makati City',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 5,
                'minimum_order_amount' => 3000,
                'categories' => ['Equipment', 'Recovery'],
            ],
            'NutriCore Pharma' => [
                'contact_person' => 'Doc Alvin Reyes',
                'email' => 'supply@nutricore.example.com',
                'phone' => '02-8801-9900',
                'address' => '21 Shaw Blvd, Mandaluyong',
                'payment_terms' => 'COD',
                'lead_time_days' => 3,
                'minimum_order_amount' => 2000,
                'categories' => ['Supplements', 'Nutrition'],
            ],
            'ActiveWear Co.' => [
                'contact_person' => 'Tess Active',
                'email' => 'sales@activewear.example.com',
                'phone' => '02-8877-2210',
                'address' => '9 Banawe St, Quezon City',
                'payment_terms' => 'Net 30',
                'lead_time_days' => 10,
                'minimum_order_amount' => 2500,
                'categories' => ['Apparel'],
            ],
            'HydroGear' => [
                'contact_person' => 'Jun Hydro',
                'email' => 'orders@hydrogear.example.com',
                'phone' => '02-8833-4412',
                'address' => '77 EDSA, Caloocan',
                'payment_terms' => 'Net 15',
                'lead_time_days' => 4,
                'minimum_order_amount' => 1500,
                'categories' => ['Accessories', 'Apparel'],
            ],
        ];

        $categoryIds = Category::pluck('id', 'name');

        foreach ($suppliers as $name => $details) {
            $categories = $details['categories'];
            unset($details['categories']);

            $supplier = Supplier::updateOrCreate(
                ['company_name' => $name],
                $details + ['is_active' => true]
            );

            Product::where('supplier', $name)->update(['supplier_id' => $supplier->id]);

            $sync = [];
            foreach ($categories as $categoryName) {
                if (isset($categoryIds[$categoryName])) {
                    $sync[] = $categoryIds[$categoryName];
                }
            }
            $supplier->categories()->sync($sync);
        }
    }
}
