<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('is_recovered_cart')->default(false)->after('sale_id');
        });

        // Limpieza del enfoque anterior: se había usado un sale_status
        // "Carrito recuperado" para marcar la venta original. Ahora la
        // original nunca cambia de estado — se revierte a "Pendiente de
        // pago" (8) y se marca la venta hija (la que la reemplazó) con
        // is_recovered_cart, antes de borrar ese estado.
        $recoveredStatusId = DB::table('sale_status')->where('name', 'Carrito recuperado')->value('id');

        if ($recoveredStatusId) {
            $recoveredParentIds = DB::table('sales')->where('sale_status_id', $recoveredStatusId)->pluck('id');

            if ($recoveredParentIds->isNotEmpty()) {
                DB::table('sales')->whereIn('sale_id', $recoveredParentIds)->update(['is_recovered_cart' => true]);
            }

            DB::table('sales')->where('sale_status_id', $recoveredStatusId)->update(['sale_status_id' => 8]);

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
