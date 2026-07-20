<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alteramos la columna para permitir el nuevo valor 'NxN'
        DB::statement("ALTER TABLE coupons MODIFY COLUMN type ENUM('Fijo', 'Porcentaje', 'NxN') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE coupons MODIFY COLUMN type ENUM('Fijo', 'Porcentaje') NOT NULL");
    }
};
