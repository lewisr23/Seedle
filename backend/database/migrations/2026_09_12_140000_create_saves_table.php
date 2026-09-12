<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Polymorphic because the same "save" gesture applies to a listing
            // and to a plant in the reference library. morphs() also indexes
            // (savable_type, savable_id), which is the lookup the restock
            // notification does — so no extra index is needed here.
            $table->morphs('savable');
            $table->timestamps();

            // Saving twice is a no-op, not a duplicate row.
            $table->unique(['user_id', 'savable_type', 'savable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saves');
    }
};
