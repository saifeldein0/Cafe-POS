<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only: existing orders keep shift_id = NULL ("before shifts") and are never touched.
 *
 * On SQLite, Laravel adds a foreign key by rebuilding the whole orders table (copy, drop, rename).
 * The owner's live database must not go through that, so SQLite gets a plain
 * "ALTER TABLE ... ADD COLUMN ... REFERENCES", which SQLite supports natively for a NULL column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE "orders" ADD COLUMN "shift_id" INTEGER NULL REFERENCES "shifts" ("id") ON DELETE SET NULL');
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shift_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['shift_id']);
            }
            $table->dropColumn('shift_id');
        });
    }
};
