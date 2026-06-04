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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('min_amount', 20, 2);
            $table->enum('type', ['Fijo', 'Porcentaje']);
            $table->boolean('applies_to_shipping')->default(false);
            $table->integer('max_use_per_user');
            $table->integer('max_use_per_code');
            $table->foreignId('coupon_status_id')->constrained('coupon_statuses');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
