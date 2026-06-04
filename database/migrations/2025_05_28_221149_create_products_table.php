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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->foreignId('product_type_id')->constrained('product_types');
            $table->foreignId('product_status_id')->constrained('product_statuses');
            $table->decimal('price', 10, 2);
            $table->decimal('discounted_price', 10, 2)->nullable();
            $table->foreignId('product_stock_status_id')->constrained('product_stock_statuses');
            $table->integer('stock_quantity')->nullable();
            $table->foreignId('tag_id')->nullable()->constrained('configuration_tags');
            $table->text('description')->nullable();
            $table->boolean('is_feature')->default(false);
            $table->string('tutorial_link')->nullable();
            $table->boolean('is_customizable')->default(false);
            $table->json('meta_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
