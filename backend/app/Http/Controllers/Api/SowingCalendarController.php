<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Garden\SowingCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SowingCalendarController extends Controller
{
    /**
     * The year's sowing and harvesting windows.
     *
     * Open to signed-out visitors, who get the reference calendar for a zone
     * with nothing marked as theirs. It is the same page either way, which
     * saves the browse-before-you-join case a special empty state.
     */
    public function index(Request $request, SowingCalendarService $calendar): JsonResponse
    {
        // Explicit guard: this route sits outside the auth middleware, and
        // the app's default guard is the session one, which knows nothing
        // about the bearer token a signed-in SPA sends.
        $user = $request->user('sanctum');
        $zone = $request->filled('zone') ? $request->integer('zone') : null;

        if ($zone === null && $user?->hardiness_zone) {
            $zone = (int) $user->hardiness_zone;
        }

        return response()->json([
            'zone' => $zone,
            'month' => (int) now()->month,
            'rows' => $calendar->forUser($user, $zone),
        ]);
    }
}
