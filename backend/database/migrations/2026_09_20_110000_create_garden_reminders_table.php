<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A record of which reminders have already gone out.
 *
 * The scheduler runs daily and asks the same questions every time, so without
 * this a "time to sow" reminder would arrive every morning for a month. The
 * unique key is what makes the command idempotent: it can be run twice, or
 * run again after a failure, without anyone being told twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garden_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 20);
            // Whatever the reminder is about: a plant for sowing, a bed entry
            // for harvesting.
            $table->unsignedBigInteger('subject_id');
            // Scopes how often it may repeat. "2026-09" for monthly advice,
            // "once" for a one-off like a crop coming ready.
            $table->string('period', 16);
            $table->timestamps();

            $table->unique(['user_id', 'kind', 'subject_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garden_reminders');
    }
};
