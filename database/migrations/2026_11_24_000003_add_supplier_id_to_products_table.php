<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lab 6 stored the supplier as a free-text name. Lab 7 turns it into a
     * real relationship and back-fills existing rows so no data is lost.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('supplier')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('package_size')->default(1)->after('supplier_id');
            $table->decimal('daily_usage_override', 8, 2)->nullable()->after('package_size');
            $table->unsignedInteger('safety_stock_override')->nullable()->after('daily_usage_override');
        });

        // Create one supplier per distinct legacy name, then link products.
        $names = DB::table('products')
            ->whereNotNull('supplier')
            ->where('supplier', '!=', '')
            ->distinct()
            ->pluck('supplier');

        $now = now();

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $existing = DB::table('suppliers')->where('company_name', $name)->first();

            $supplierId = $existing?->id ?? DB::table('suppliers')->insertGetId([
                'company_name' => $name,
                'contact_person' => 'Procurement Contact',
                'email' => 'supplier'.DB::table('suppliers')->count().'@jimtrack.local',
                'phone' => null,
                'address' => null,
                'payment_terms' => 'Net 30',
                'lead_time_days' => 7,
                'minimum_order_amount' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('products')->where('supplier', $name)->update(['supplier_id' => $supplierId]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'supplier_id',
                'package_size',
                'daily_usage_override',
                'safety_stock_override',
            ]);
        });
    }
};
