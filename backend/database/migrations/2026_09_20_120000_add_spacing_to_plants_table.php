<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much room a plant needs, in centimetres.
 *
 * The plot planner draws each plant at its real footprint rather than as a
 * uniform dot, which is the whole point of laying a bed out to scale: four
 * courgettes genuinely will not fit where sixteen radishes would.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->unsignedSmallInteger('spacing_cm')->nullable()->after('days_to_maturity');
        });
    }

    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->dropColumn('spacing_cm');
        });
    }
};
