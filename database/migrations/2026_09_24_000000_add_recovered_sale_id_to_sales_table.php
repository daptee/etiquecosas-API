<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sales', 'recovered_sale_id')) {
            Schema::table('sales', function (Blueprint $table) {
                // sales.id es BIGINT (signed) en este servidor, no UNSIGNED,
                // así que no se puede usar foreignId() (que siempre crea
                // unsignedBigInteger) — mismo motivo que sale_id.
                $table->bigInteger('recovered_sale_id')->nullable()->after('sale_id');
            });

            Schema::table('sales', function (Blueprint $table) {
                $table->foreign('recovered_sale_id')->references('id')->on('sales');
            });
        }

        // sale_id se reserva exclusivamente para la asociación manual de
        // ventas del admin. Hasta ahora la recuperación de carrito abandonado
        // usaba ese mismo campo por error — se migran esos datos a
        // recovered_sale_id y se limpia sale_id en esos casos.
        DB::statement('
            UPDATE sales child
            INNER JOIN sales parent ON parent.id = child.sale_id
            SET child.recovered_sale_id = child.sale_id, child.sale_id = NULL
            WHERE parent.is_recovered_cart = 1
        ');
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['recovered_sale_id']);
            $table->dropColumn('recovered_sale_id');
        });
    }
};
