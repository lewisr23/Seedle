<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('plants')->cascadeOnDelete();
            $table->foreignId('companion_plant_id')->constrained('plants')->cascadeOnDelete();
            $table->enum('relationship', ['good', 'bad']);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['plant_id', 'companion_plant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_companions');
    }
};
