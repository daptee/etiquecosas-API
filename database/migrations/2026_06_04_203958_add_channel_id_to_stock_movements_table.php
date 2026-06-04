<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'channel_id')) {
                // La columna puede haber quedado como UNSIGNED de un intento previo fallido
                $table->bigInteger('channel_id')->nullable()->change();
            } else {
                $table->bigInteger('channel_id')->nullable()->after('sale_id');
            }

            $table->foreign('channel_id')->references('id')->on('channels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
            $table->dropColumn('channel_id');
        });
    }
};
