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
         Schema::create('general_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
        });

        DB::table('general_statuses')->insert([
            ['id' => 1, 'name' => 'Activo'],
            ['id' => 2, 'name' => 'Inactivo'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_statuses');
    }
};
