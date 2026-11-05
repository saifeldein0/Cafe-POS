<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('packages', 10, 3);
            $table->decimal('quantity', 10, 3);
            $table->decimal('total_cost', 8, 3);
            $table->date('purchased_at');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('purchased_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
