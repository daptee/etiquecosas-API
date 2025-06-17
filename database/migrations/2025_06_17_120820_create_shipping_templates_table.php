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
        Schema::create('shipping_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_shipping_template_category')->constrained('templates_categories');
            $table->text('description')->nullable();
            $table->foreignId('status_id')->default(1)->constrained('general_statuses');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_templates');
    }
};
