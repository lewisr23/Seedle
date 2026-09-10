<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garden_bed_plants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garden_bed_id')->constrained('garden_beds')->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained('plants')->cascadeOnDelete();
            $table->date('planted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garden_bed_plants');
    }
};
