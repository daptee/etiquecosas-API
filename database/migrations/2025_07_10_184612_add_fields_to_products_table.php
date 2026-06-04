<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('wholesale_price', 10, 2)->nullable()->after('stock_quantity');
            $table->integer('wholesale_min_amount')->nullable()->after('wholesale_price');
            $table->json('costs')->nullable()->after('wholesale_min_amount');
            $table->json('wholesales')->nullable()->after('costs');
            $table->string('shortDescription', 500)->nullable()->after('description');
            $table->text('shipping_text')->nullable()->after('shortDescription');
            $table->text('shipping_time_text')->nullable()->after('shipping_text');
            $table->text('notifications_text')->nullable()->after('shipping_time_text');
            $table->json('related_products')->nullable()->after('notifications_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'wholesale_price',
                'wholesale_min_amount',
                'costs',
                'wholesales',
                'shortDescription',
                'shipping_text',
                'shipping_time_text',
                'notifications_text',
                'related_products',
            ]);
        });
    }
};
