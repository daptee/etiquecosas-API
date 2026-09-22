<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sales', 'is_recovered_cart')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->boolean('is_recovered_cart')->default(false)->after('sale_id');
            });
        }

        // Limpieza del enfoque anterior: se había usado un sale_status
        // "Carrito recuperado" para marcar la venta original. Ahora la
        // original nunca cambia de estado — se revierte a "Pendiente de
        // pago" (8) y se marca ella misma (no la hija) con
        // is_recovered_cart, antes de borrar ese estado.
        $recoveredStatusId = DB::table('sale_status')->where('name', 'Carrito recuperado')->value('id');

        if ($recoveredStatusId) {
            DB::table('sales')
                ->where('sale_status_id', $recoveredStatusId)
                ->update(['is_recovered_cart' => true, 'sale_status_id' => 8]);

            // El historial de estados también apunta a este sale_status;
            // hay que borrarlo antes de poder borrar el status (FK).
            DB::table('sales_status_history')->where('sale_status_id', $recoveredStatusId)->delete();

            DB::table('sale_status')->where('id', $recoveredStatusId)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('is_recovered_cart');
        });
    }
};
