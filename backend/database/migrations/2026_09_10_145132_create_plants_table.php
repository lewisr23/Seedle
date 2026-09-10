<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scientific_name')->nullable();
            $table->string('slug')->unique();
            $table->enum('type', ['vegetable', 'fruit', 'herb', 'flower', 'tree', 'shrub']);
            $table->enum('sun_requirement', ['full_sun', 'partial_sun', 'shade']);
            $table->enum('water_needs', ['low', 'medium', 'high']);
            $table->string('soil_type')->nullable();
            $table->unsignedTinyInteger('min_zone');
            $table->unsignedTinyInteger('max_zone');
            $table->unsignedSmallInteger('days_to_maturity')->nullable();
            $table->json('planting_months');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['min_zone', 'max_zone']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plants');
    }
};
