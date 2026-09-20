<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The other half of a swap community: asking, not just offering. Without it
 * a new member with nothing to give has nothing to do, and a shelf with
 * nothing on it looks dead rather than quiet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Optional link to the plant library. When set, a new listing for
            // the same plant can tell the asker about it.
            $table->foreignId('plant_id')->nullable()->constrained('plants')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->index(['is_open', 'created_at']);
            $table->index(['plant_id', 'is_open']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wants');
    }
};
