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
        Schema::create('templates_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->foreignId('status_id')->default(1)->constrained('general_statuses');
            $table->timestamps();
        });

        DB::table('templates_categories')->insert([
            ['name' => 'Entrega'],
            ['name' => 'Tiempos de envío'],
            ['name' => 'Notificaciones'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_categories');
    }
};
