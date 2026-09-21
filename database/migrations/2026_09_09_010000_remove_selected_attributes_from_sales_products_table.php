<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            if (Schema::hasColumn('sales_products', 'selected_attributes')) {
                $table->dropColumn('selected_attributes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            $table->json('selected_attributes')->nullable()->after('customization_data');
        });
    }
};
