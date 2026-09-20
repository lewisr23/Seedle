<?php

namespace App\Services\Location;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a UK postcode into an approximate point, using postcodes.io.
 *
 * Two deliberate choices. Results are cached, because a postcode's location
 * does not change and the service should not be hit twice for the same one.
 * And a failure returns null rather than throwing: not knowing where someone
 * is should cost them the distance filter, not the ability to save a profile.
 */
class PostcodeGeocoder
{
    private const ENDPOINT = 'https://api.postcodes.io/postcodes/';

    /** Roughly a kilometre, so a point lands in a neighbourhood, not a garden. */
    private const PRECISION = 2;

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function lookup(string $postcode): ?array
    {
        $normalised = $this->normalise($postcode);

        if ($normalised === '') {
            return null;
        }

        return Cache::remember(
            "postcode:{$normalised}",
            now()->addDays(30),
            fn () => $this->fetch($normalised)
        );
    }

    public function normalise(string $postcode): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($postcode)) ?? '');
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function fetch(string $postcode): ?array
    {
        try {
            $response = Http::timeout(5)->get(self::ENDPOINT.urlencode($postcode));

            if (! $response->successful()) {
                return null;
            }

            $result = $response->json('result');

            if (! is_array($result) || ! isset($result['latitude'], $result['longitude'])) {
                return null;
            }

            return [
                'latitude' => round((float) $result['latitude'], self::PRECISION),
                'longitude' => round((float) $result['longitude'], self::PRECISION),
            ];
        } catch (Throwable $e) {
            Log::warning('Postcode lookup failed.', [
                'postcode' => $postcode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
