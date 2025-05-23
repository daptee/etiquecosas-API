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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('SET NULL');
            $table->string('name', 100);
            $table->string('img')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 50)->nullable();
            $table->text('meta_data')->nullable();
            $table->text('description')->nullable();
            $table->string('banner')->nullable()->default(null);
            $table->foreignId('status_id')->default(1)->constrained('general_status');
            $table->foreignId('tag_id')->nullable()->constrained('configuration_tags')->onDelete('SET NULL');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
