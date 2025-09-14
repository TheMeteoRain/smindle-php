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
        Schema::create('order_clients', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('order_id')
                ->constrained('orders');
            $table->string('identity');
            $table->string('contact_point');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_clients');
    }
};
