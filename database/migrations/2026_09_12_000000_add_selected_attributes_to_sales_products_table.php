<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_products', 'selected_attributes')) {
                // Valor concreto de attribute_value elegido por el cliente, para
                // variantes comodín ("Todos"). Formato:
                // [{"attribute_id": 1, "attribute_value_id": 15}, ...]
                $table->json('selected_attributes')->nullable()->after('customization_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            if (Schema::hasColumn('sales_products', 'selected_attributes')) {
                $table->dropColumn('selected_attributes');
            }
        });
    }
};
