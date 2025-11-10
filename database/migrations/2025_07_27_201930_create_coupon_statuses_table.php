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
        Schema::create('coupon_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('coupon_statuses')->insert([
            ['id' => 1, 'name' => 'Borrador'],
            ['id' => 2, 'name' => 'Activo'],
            ['id' => 3, 'name' => 'Finalizado'],
            ['id' => 4, 'name' => 'Cancelado'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_statuses');
    }
};
