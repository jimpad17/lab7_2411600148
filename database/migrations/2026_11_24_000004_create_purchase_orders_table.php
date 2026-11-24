<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Lab 7 - formal supplier purchase orders with an approval workflow. */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft')
                ->comment('draft, pending, approved, sent, received, rejected, cancelled');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('terms', 100)->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // created by
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('auto_generated')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'order_date']);
            $table->index(['supplier_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
