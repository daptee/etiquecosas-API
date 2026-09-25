<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abandoned_cart_logs', function (Blueprint $table) {
            $table->uuid('uid')->nullable()->unique()->after('sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('abandoned_cart_logs', function (Blueprint $table) {
            $table->dropColumn('uid');
        });
    }
};
