<?php

namespace App\Http\Requests;

use App\Models\GardenBed;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared bounds checking for a plant's spot on a bed's plan.
 *
 * The rules array can only say "a sensible number of centimetres"; whether
 * 180cm is inside the bed depends on the bed, which is why this runs as an
 * after-hook with the route model in hand.
 */
trait ValidatesPlotPosition
{
    protected function validatePositionWithin(Validator $validator, GardenBed $bed): void
    {
        $validator->after(function (Validator $validator) use ($bed) {
            $x = $this->input('x_cm');
            $y = $this->input('y_cm');

            if ($x === null && $y === null) {
                return;
            }

            // A position is a pair. Half of one places the plant nowhere in
            // particular, and silently keeping the old other half would put it
            // somewhere the gardener never pointed at.
            if ($x === null || $y === null) {
                $validator->errors()->add('x_cm', 'A position needs both an across and a down measurement.');

                return;
            }

            if (! $bed->hasPlot()) {
                $validator->errors()->add('x_cm', 'Give this bed a width and length before placing plants on it.');

                return;
            }

            if ((int) $x > $bed->width_cm) {
                $validator->errors()->add('x_cm', "That is past the edge of the bed, which is {$bed->width_cm}cm across.");
            }

            if ((int) $y > $bed->length_cm) {
                $validator->errors()->add('y_cm', "That is past the edge of the bed, which is {$bed->length_cm}cm deep.");
            }
        });
    }
}
