<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What actually came out of the ground.
 *
 * Hangs off the bed entry rather than the plant, so "the courgettes in the
 * top bed" and "the courgettes in the raised bed" stay separate tallies. The
 * denormalised user_id is worth its keep: the log and the year summary are
 * both "everything I picked", which otherwise means joining through beds on
 * every read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garden_bed_plant_id')->constrained('garden_bed_plants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('harvested_at');
            // Nullable because "picked some" is a legitimate entry: a note that
            // the crop came good is worth more than nothing at all.
            $table->decimal('quantity', 8, 2)->nullable();
            $table->string('unit', 16)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'harvested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvests');
    }
};
