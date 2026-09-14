<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listings are swaps and giveaways rather than sales, so nothing carries a
 * price any more. The claim pipeline is unchanged: stock still has to be
 * locked and decremented, because two people claiming the last packet is the
 * same race as two people buying it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price_pence');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_pence');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('unit_price_pence');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('price_pence')->default(0);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('total_pence')->default(0);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('unit_price_pence')->default(0);
        });
    }
};
