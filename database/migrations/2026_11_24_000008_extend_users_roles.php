<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lab 7 adds manager (approval level 1) and warehouse (goods receiving)
     * roles alongside the existing admin / staff roles.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin','manager','warehouse','staff') NOT NULL DEFAULT 'staff'");

            return;
        }

        // SQLite stores the enum as text; nothing to alter there.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Keep any manager/warehouse rows by converting them to staff first.
            DB::table('users')->whereIn('role', ['manager', 'warehouse'])->update(['role' => 'staff']);
            DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin','staff') NOT NULL DEFAULT 'staff'");
        }
    }
};
