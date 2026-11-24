<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lab 7 - supplier master data. Soft deletes keep historical purchase
     * orders referentially intact while allowing a supplier to be "removed".
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 150);
            $table->string('contact_person', 100)->nullable();
            $table->string('email', 150)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('payment_terms', 50)->default('Net 30');
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->decimal('minimum_order_amount', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_active', 'company_name']);
            $table->index('lead_time_days');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
