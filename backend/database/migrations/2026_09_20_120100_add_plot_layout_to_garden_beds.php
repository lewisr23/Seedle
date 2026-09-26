<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a bed a physical size and its entries a position within it.
 *
 * All nullable on purpose. A bed that predates the planner is still a valid
 * bed, it just has no plan drawn for it yet, and an entry with no coordinates
 * is "growing in here somewhere" rather than a point on the map. The planner
 * treats those as unplaced and offers to drop them onto the plot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garden_beds', function (Blueprint $table) {
            $table->unsignedSmallInteger('width_cm')->nullable()->after('hardiness_zone');
            $table->unsignedSmallInteger('length_cm')->nullable()->after('width_cm');
        });

        Schema::table('garden_bed_plants', function (Blueprint $table) {
            // Centre of the plant, measured from the bed's top-left corner.
            $table->unsignedSmallInteger('x_cm')->nullable()->after('plant_id');
            $table->unsignedSmallInteger('y_cm')->nullable()->after('x_cm');
        });
    }

    public function down(): void
    {
        Schema::table('garden_beds', function (Blueprint $table) {
            $table->dropColumn(['width_cm', 'length_cm']);
        });

        Schema::table('garden_bed_plants', function (Blueprint $table) {
            $table->dropColumn(['x_cm', 'y_cm']);
        });
    }
};
