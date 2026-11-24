<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Many-to-many relationship: a supplier can cover several product
     * categories and a category can be sourced from several suppliers.
     */
    public function up(): void
    {
        Schema::create('supplier_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_categories');
    }
};
