<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('unit', 10); // g, ml, piece
            $table->decimal('current_stock', 10, 3)->default(0);
            $table->string('package_name')->nullable();
            $table->decimal('units_per_package', 10, 3)->default(1);
            $table->decimal('last_unit_cost', 10, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
