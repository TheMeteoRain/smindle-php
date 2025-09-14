<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_contents', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('order_id')
                ->constrained('orders');
            $table->string('label');
            $table->enum('kind', ['single', 'recurring'])->default('single');
            $table->decimal('cost', 8, 2);
            $table->enum('status', ['received', 'failed', 'processing', 'completed'])->default('completed');
            $table->json('meta')->nullable();
            $table->json('details')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_contents');
    }
};
