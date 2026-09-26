<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\PlantCompanion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlantSeeder extends Seeder
{
    /**
     * Curated, real companion-planting reference data.
     * Zones are USDA-style hardiness zones (approximate, for demo purposes).
     * planting_months are typical UK outdoor sow/plant-out months (1=Jan .. 12=Dec).
     * spacing is the recommended gap between plants in centimetres, used to
     * draw each plant at its true footprint in the plot planner.
     */
    private const PLANTS = [
        ['name' => 'Tomato', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'high', 'soil' => 'loamy', 'zone' => [3, 11], 'days' => 75, 'spacing' => 45, 'months' => [5, 6], 'desc' => 'A garden staple. Needs support as it grows and consistent watering to avoid split fruit.'],
        ['name' => 'Basil', 'type' => 'herb', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'well-drained', 'zone' => [4, 11], 'days' => 60, 'spacing' => 25, 'months' => [5, 6], 'desc' => 'Loves warmth. Pinch off flower buds to keep leaves productive.'],
        ['name' => 'Carrot', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'sandy', 'zone' => [3, 10], 'days' => 70, 'spacing' => 8, 'months' => [3, 4, 5, 6, 7], 'desc' => 'Needs loose, stone-free soil to grow straight roots.'],
        ['name' => 'Onion', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'low', 'soil' => 'well-drained', 'zone' => [3, 9], 'days' => 100, 'spacing' => 10, 'months' => [3, 4, 9, 10], 'desc' => 'Plant as sets for the easiest results. Stop watering once tops begin to fall over.'],
        ['name' => 'Green Bean', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'loamy', 'zone' => [3, 10], 'days' => 55, 'spacing' => 15, 'months' => [5, 6], 'desc' => 'Fixes nitrogen in the soil, making it a great bed neighbour for hungry feeders.'],
        ['name' => 'Cabbage', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'high', 'soil' => 'loamy', 'zone' => [1, 9], 'days' => 90, 'spacing' => 45, 'months' => [3, 4, 7, 8], 'desc' => 'A heavy feeder: enrich the soil with compost before planting.'],
        ['name' => 'Cucumber', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'high', 'soil' => 'loamy', 'zone' => [4, 11], 'days' => 60, 'spacing' => 45, 'months' => [5, 6], 'desc' => 'Grows well up a trellis to save ground space and keep fruit clean.'],
        ['name' => 'Bell Pepper', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'loamy', 'zone' => [4, 11], 'days' => 75, 'spacing' => 40, 'months' => [5, 6], 'desc' => 'Needs warmth to set fruit. A greenhouse or sunny sheltered spot helps in cooler climates.'],
        ['name' => 'Potato', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'loamy', 'zone' => [1, 10], 'days' => 90, 'spacing' => 35, 'months' => [3, 4], 'desc' => 'Earth up around the stems as they grow to stop tubers turning green.'],
        ['name' => 'Pea', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'well-drained', 'zone' => [2, 9], 'days' => 60, 'spacing' => 8, 'months' => [3, 4, 9], 'desc' => 'A cool-season crop: sow early before the weather turns hot.'],
        ['name' => 'Lettuce', 'type' => 'vegetable', 'sun' => 'partial_sun', 'water' => 'medium', 'soil' => 'loamy', 'zone' => [2, 10], 'days' => 45, 'spacing' => 25, 'months' => [3, 4, 5, 8, 9], 'desc' => 'Sow little and often for a steady supply rather than one big crop that bolts.'],
        ['name' => 'Radish', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'sandy', 'zone' => [2, 10], 'days' => 25, 'spacing' => 5, 'months' => [3, 4, 5, 8, 9], 'desc' => 'One of the fastest vegetables to harvest, great for impatient gardeners.'],
        ['name' => 'Beetroot', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'loamy', 'zone' => [2, 10], 'days' => 60, 'spacing' => 10, 'months' => [4, 5, 6], 'desc' => 'Both the root and the leaves are edible.'],
        ['name' => 'Sweetcorn', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'high', 'soil' => 'loamy', 'zone' => [3, 10], 'days' => 80, 'spacing' => 35, 'months' => [5], 'desc' => 'Plant in blocks rather than rows to help it pollinate properly.'],
        ['name' => 'Pumpkin', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'high', 'soil' => 'loamy', 'zone' => [3, 10], 'days' => 100, 'spacing' => 90, 'months' => [5], 'desc' => 'Needs a lot of space to sprawl, or grow it up a very sturdy support.'],
        ['name' => 'Strawberry', 'type' => 'fruit', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'loamy', 'zone' => [3, 10], 'days' => 90, 'spacing' => 35, 'months' => [3, 4, 9], 'desc' => 'Put straw under ripening fruit to keep it off damp soil.'],
        ['name' => 'Spinach', 'type' => 'vegetable', 'sun' => 'partial_sun', 'water' => 'medium', 'soil' => 'loamy', 'zone' => [2, 9], 'days' => 40, 'spacing' => 15, 'months' => [3, 4, 8, 9], 'desc' => 'Bolts quickly in hot weather, so it prefers cooler spring or autumn growing.'],
        ['name' => 'Garlic', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'low', 'soil' => 'well-drained', 'zone' => [3, 9], 'days' => 240, 'spacing' => 15, 'months' => [10, 11], 'desc' => 'Plant cloves in autumn for harvest the following summer.'],
        ['name' => 'Rosemary', 'type' => 'herb', 'sun' => 'full_sun', 'water' => 'low', 'soil' => 'well-drained', 'zone' => [7, 10], 'days' => 180, 'spacing' => 60, 'months' => [4, 5], 'desc' => 'A drought-tolerant perennial herb: don\'t overwater it.'],
        ['name' => 'Sage', 'type' => 'herb', 'sun' => 'full_sun', 'water' => 'low', 'soil' => 'well-drained', 'zone' => [4, 10], 'days' => 75, 'spacing' => 45, 'months' => [4, 5], 'desc' => 'Cut back hard in spring to keep the plant bushy rather than woody.'],
        ['name' => 'Marigold', 'type' => 'flower', 'sun' => 'full_sun', 'water' => 'low', 'soil' => 'well-drained', 'zone' => [2, 11], 'days' => 50, 'spacing' => 20, 'months' => [5, 6], 'desc' => 'A classic companion flower, its scent helps deter several common garden pests.'],
        ['name' => 'Sunflower', 'type' => 'flower', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'loamy', 'zone' => [2, 11], 'days' => 80, 'spacing' => 45, 'months' => [4, 5], 'desc' => 'Tall varieties may need staking once the flower head develops.'],
        ['name' => 'Chilli Pepper', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'medium', 'soil' => 'loamy', 'zone' => [5, 11], 'days' => 90, 'spacing' => 40, 'months' => [5, 6], 'desc' => 'The hotter the variety, the more heat and sunshine it usually needs to ripen well.'],
        ['name' => 'Courgette', 'type' => 'vegetable', 'sun' => 'full_sun', 'water' => 'high', 'soil' => 'loamy', 'zone' => [3, 10], 'days' => 50, 'spacing' => 90, 'months' => [5, 6], 'desc' => 'Extremely productive: one or two plants is usually plenty for a household.'],
    ];

    /**
     * plant name => [good companions], [bad companions]
     */
    private const COMPANIONS = [
        'Tomato' => ['good' => ['Basil', 'Carrot', 'Marigold'], 'bad' => ['Cabbage', 'Potato', 'Sweetcorn']],
        'Basil' => ['good' => ['Tomato', 'Bell Pepper', 'Chilli Pepper'], 'bad' => []],
        'Carrot' => ['good' => ['Tomato', 'Onion', 'Pea', 'Lettuce'], 'bad' => []],
        'Onion' => ['good' => ['Carrot', 'Beetroot', 'Cabbage'], 'bad' => ['Green Bean', 'Pea']],
        'Green Bean' => ['good' => ['Carrot', 'Cucumber', 'Sweetcorn'], 'bad' => ['Onion', 'Garlic']],
        'Cabbage' => ['good' => ['Onion', 'Rosemary', 'Sage'], 'bad' => ['Tomato', 'Strawberry']],
        'Cucumber' => ['good' => ['Green Bean', 'Sweetcorn', 'Pea', 'Sunflower'], 'bad' => ['Potato', 'Sage']],
        'Bell Pepper' => ['good' => ['Basil', 'Onion'], 'bad' => []],
        'Potato' => ['good' => ['Green Bean', 'Sweetcorn', 'Cabbage'], 'bad' => ['Tomato', 'Cucumber', 'Pumpkin', 'Sunflower']],
        'Pea' => ['good' => ['Carrot', 'Cucumber', 'Sweetcorn', 'Radish'], 'bad' => ['Onion', 'Garlic']],
        'Lettuce' => ['good' => ['Carrot', 'Radish', 'Cucumber', 'Strawberry'], 'bad' => []],
        'Radish' => ['good' => ['Lettuce', 'Pea', 'Cucumber'], 'bad' => []],
        'Beetroot' => ['good' => ['Onion', 'Cabbage'], 'bad' => []],
        'Sweetcorn' => ['good' => ['Green Bean', 'Cucumber', 'Pumpkin'], 'bad' => ['Tomato']],
        'Pumpkin' => ['good' => ['Sweetcorn'], 'bad' => ['Potato']],
        'Strawberry' => ['good' => ['Green Bean', 'Lettuce', 'Spinach'], 'bad' => ['Cabbage']],
        'Spinach' => ['good' => ['Strawberry', 'Pea'], 'bad' => []],
        'Garlic' => ['good' => ['Tomato', 'Cabbage'], 'bad' => ['Green Bean', 'Pea']],
        'Rosemary' => ['good' => ['Cabbage', 'Green Bean'], 'bad' => []],
        'Sage' => ['good' => ['Cabbage', 'Carrot'], 'bad' => ['Cucumber']],
        'Marigold' => ['good' => ['Tomato'], 'bad' => []],
        'Sunflower' => ['good' => ['Cucumber'], 'bad' => ['Potato']],
        'Chilli Pepper' => ['good' => ['Basil'], 'bad' => []],
        'Courgette' => ['good' => ['Sweetcorn', 'Green Bean'], 'bad' => ['Potato']],
    ];

    public function run(): void
    {
        $slugs = [];

        foreach (self::PLANTS as $data) {
            $plant = Plant::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'scientific_name' => null,
                    'type' => $data['type'],
                    'sun_requirement' => $data['sun'],
                    'water_needs' => $data['water'],
                    'soil_type' => $data['soil'],
                    'min_zone' => $data['zone'][0],
                    'max_zone' => $data['zone'][1],
                    'days_to_maturity' => $data['days'],
                    'planting_months' => $data['months'],
                    'spacing_cm' => $data['spacing'],
                    'description' => $data['desc'],
                ]
            );

            $slugs[$data['name']] = $plant->id;
        }

        foreach (self::COMPANIONS as $name => $relations) {
            $plantId = $slugs[$name];

            foreach (['good', 'bad'] as $relationship) {
                foreach ($relations[$relationship] as $companionName) {
                    $companionId = $slugs[$companionName];

                    // Store the relationship symmetrically so a lookup from either plant works.
                    foreach ([[$plantId, $companionId], [$companionId, $plantId]] as [$a, $b]) {
                        PlantCompanion::updateOrCreate(
                            ['plant_id' => $a, 'companion_plant_id' => $b],
                            ['relationship' => $relationship]
                        );
                    }
                }
            }
        }
    }
}
