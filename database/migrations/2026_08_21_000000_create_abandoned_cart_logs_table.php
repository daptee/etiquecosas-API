<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_cart_logs', function (Blueprint $table) {
            $table->id();
            // sales.id es BIGINT (signed) en este servidor, no UNSIGNED, así que
            // no se puede usar foreignId() (que siempre crea unsignedBigInteger).
            $table->bigInteger('sale_id')->unique();
            $table->foreign('sale_id')->references('id')->on('sales');
            $table->string('client_email');
            $table->decimal('total', 10, 2);
            $table->timestamp('abandoned_at')->index();
            $table->timestamp('impact_1_sent_at')->nullable();
            $table->boolean('impact_2_eligible')->default(false);
            $table->timestamp('impact_2_sent_at')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons');
            $table->timestamp('converted_at')->nullable()->index();
            $table->enum('converted_via', ['impact_1', 'impact_2'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_cart_logs');
    }
};
