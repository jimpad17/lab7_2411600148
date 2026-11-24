<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polymorphic audit trail: the same `approvals` table can record actions
     * for purchase orders (and any other future subject model).
     */
    public function up(): void
    {
        Schema::create('purchase_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30); // submitted, approved, rejected, sent, received, cancelled
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_approvals');
    }
};
