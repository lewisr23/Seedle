<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approximate coordinates so people can find swaps they can actually collect.
 *
 * Deliberately coarse: the stored point is rounded to roughly a kilometre, so
 * it places someone in a neighbourhood rather than at a front door. Only the
 * distance between two users is ever exposed, never the point itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('postcode', 10)->nullable()->after('location');
            $table->decimal('latitude', 8, 4)->nullable()->after('postcode');
            $table->decimal('longitude', 8, 4)->nullable()->after('latitude');

            // Every radius query starts by narrowing to a bounding box.
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropColumn(['postcode', 'latitude', 'longitude']);
        });
    }
};
