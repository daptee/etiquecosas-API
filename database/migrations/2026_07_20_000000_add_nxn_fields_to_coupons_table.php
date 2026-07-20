<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // Permitimos nxn_buy (lleva N) y nxn_pay (paga M)
            $table->unsignedInteger('nxn_buy')->nullable()->after('value');
            $table->unsignedInteger('nxn_pay')->nullable()->after('nxn_buy');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['nxn_buy', 'nxn_pay']);
        });
    }
};
