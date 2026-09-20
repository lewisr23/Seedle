<?php

namespace App\Services\Search;

use App\Models\Product;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Product search backed by Elasticsearch, with a plain MySQL fallback.
 *
 * Elasticsearch gives us fast full-text search plus facet counts (how many
 * results per category/sun-requirement) in a single query. If the cluster
 * is unreachable: e.g. running locally without Docker. We degrade to a
 * MySQL LIKE query so the marketplace keeps working, just without facets.
 */
class ProductSearchService
{
    private ?Client $resolved = null;

    /**
     * Resolved on first use rather than injected, so a missing or malformed
     * ELASTICSEARCH_HOST surfaces inside the guarded calls below and degrades
     * to the database path. Injecting it would throw during construction,
     * before any fallback could catch it, and take the whole app down.
     */
    private function client(): Client
    {
        return $this->resolved ??= app(Client::class);
    }

    private function index(): string
    {
        return config('elasticsearch.indices.products');
    }

    /**
     * @param  array{q?: string, category?: string, sun_requirement?: string, zone?: int, seller_id?: int, plant_id?: int, in_stock?: bool, sort?: string}  $filters
     */
    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        try {
            return $this->searchElasticsearch($filters, $page, $perPage);
        } catch (Throwable $e) {
            Log::warning('Elasticsearch search failed, falling back to database search.', [
                'error' => $e->getMessage(),
            ]);

            return $this->searchDatabase($filters, $page, $perPage);
        }
    }

    private function searchElasticsearch(array $filters, int $page, int $perPage): array
    {
        $must = [];
        $filter = [['term' => ['is_active' => true]]];

        if (! empty($filters['q'])) {
            $must[] = [
                'multi_match' => [
                    'query' => $filters['q'],
                    'fields' => ['title^3', 'description'],
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        if (! empty($filters['category'])) {
            $filter[] = ['term' => ['category' => $filters['category']]];
        }

        if (! empty($filters['sun_requirement'])) {
            $filter[] = ['term' => ['sun_requirement' => $filters['sun_requirement']]];
        }

        if (! empty($filters['zone'])) {
            $filter[] = ['range' => ['min_zone' => ['lte' => (int) $filters['zone']]]];
            $filter[] = ['range' => ['max_zone' => ['gte' => (int) $filters['zone']]]];
        }

        if (! empty($filters['seller_id'])) {
            $filter[] = ['term' => ['seller_id' => (int) $filters['seller_id']]];
        }

        if (! empty($filters['plant_id'])) {
            $filter[] = ['term' => ['plant_id' => (int) $filters['plant_id']]];
        }

        if (! empty($filters['in_stock'])) {
            $filter[] = ['range' => ['stock' => ['gt' => 0]]];
        }

        if ($this->hasOrigin($filters)) {
            $filter[] = ['geo_distance' => [
                'distance' => ((float) $filters['radius_km']).'km',
                'seller_location' => [
                    'lat' => (float) $filters['origin_lat'],
                    'lon' => (float) $filters['origin_lon'],
                ],
            ]];
        }

        $query = empty($must) && empty($filter)
            ? ['match_all' => new \stdClass]
            : ['bool' => array_filter(['must' => $must, 'filter' => $filter])];

        $response = $this->client()->search([
            'index' => $this->index(),
            'body' => [
                'query' => $query,
                'from' => ($page - 1) * $perPage,
                'size' => $perPage,
                'sort' => $this->esSortClause($filters),
                'aggs' => [
                    'categories' => ['terms' => ['field' => 'category', 'size' => 10]],
                    'sun_requirements' => ['terms' => ['field' => 'sun_requirement', 'size' => 10]],
                ],
            ],
        ])->asArray();

        $ids = array_map(fn ($hit) => (int) $hit['_id'], $response['hits']['hits']);

        // Re-fetch from MySQL to guarantee fresh, fully-related data rather than
        // trusting the (possibly stale) denormalised copy stored in the index.
        $products = Product::with(['seller', 'plant'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids))
            ->values();

        return [
            'source' => 'elasticsearch',
            'total' => $response['hits']['total']['value'] ?? count($ids),
            'page' => $page,
            'per_page' => $perPage,
            'results' => $products,
            'facets' => [
                'category' => $this->bucketsToCounts($response['aggregations']['categories']['buckets'] ?? []),
                'sun_requirement' => $this->bucketsToCounts($response['aggregations']['sun_requirements']['buckets'] ?? []),
            ],
        ];
    }

    private function bucketsToCounts(array $buckets): array
    {
        return collect($buckets)->mapWithKeys(fn ($b) => [$b['key'] => $b['doc_count']])->all();
    }

    private function esSortClause(array $filters): array
    {
        if (($filters['sort'] ?? null) === 'distance' && $this->hasOrigin($filters)) {
            return [[
                '_geo_distance' => [
                    'seller_location' => [
                        'lat' => (float) $filters['origin_lat'],
                        'lon' => (float) $filters['origin_lon'],
                    ],
                    'order' => 'asc',
                    'unit' => 'km',
                ],
            ]];
        }

        return match ($filters['sort'] ?? null) {
            'rating_desc' => [['rating_average' => ['order' => 'desc', 'missing' => '_last']]],
            default => empty($filters['q']) ? [['created_at' => 'desc']] : ['_score'],
        };
    }

    /** A radius search needs both a centre and a distance to be meaningful. */
    private function hasOrigin(array $filters): bool
    {
        return isset($filters['origin_lat'], $filters['origin_lon'], $filters['radius_km'])
            && is_numeric($filters['origin_lat'])
            && is_numeric($filters['origin_lon'])
            && (float) $filters['radius_km'] > 0;
    }

    /**
     * Distance without PostGIS or spatial indexes: narrow with a cheap
     * bounding box the (latitude, longitude) index can serve, then compute the
     * real great-circle distance only for what survives.
     */
    private function applyDatabaseRadius($query, array $filters): void
    {
        $lat = (float) $filters['origin_lat'];
        $lon = (float) $filters['origin_lon'];
        $radius = (float) $filters['radius_km'];

        $latDelta = $radius / 111.0;
        // Lines of longitude converge towards the poles, so the box has to
        // widen with latitude or it clips results to the east and west.
        $lonDelta = $radius / max(cos(deg2rad($lat)) * 111.0, 0.000001);

        $query->join('users as seller_loc', 'seller_loc.id', '=', 'products.seller_id')
            ->whereNotNull('seller_loc.latitude')
            ->whereNotNull('seller_loc.longitude')
            ->whereBetween('seller_loc.latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('seller_loc.longitude', [$lon - $lonDelta, $lon + $lonDelta])
            ->select('products.*')
            ->selectRaw($this->haversineSql().' as distance_km', [$lat, $lat, $lon])
            // WHERE rather than HAVING on the alias: MySQL accepts HAVING
            // without a GROUP BY, SQLite does not, so the expression is
            // repeated through one shared builder instead.
            ->whereRaw($this->haversineSql().' <= ?', [$lat, $lat, $lon, $radius]);
    }

    /**
     * Haversine rather than the spherical law of cosines: it needs no
     * clamping against floating point drift, which would otherwise mean
     * LEAST and GREATEST, and those do not exist in SQLite.
     *
     * Takes three bindings, in order: origin latitude twice, then longitude.
     */
    private function haversineSql(): string
    {
        return '(2 * 6371 * asin(sqrt('
            .' power(sin((radians(seller_loc.latitude) - radians(?)) / 2), 2)'
            .' + cos(radians(?)) * cos(radians(seller_loc.latitude))'
            .' * power(sin((radians(seller_loc.longitude) - radians(?)) / 2), 2)'
            .')))';
    }

    private function searchDatabase(array $filters, int $page, int $perPage): array
    {
        $query = Product::query()
            ->with(['seller', 'plant'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('is_active', true);

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term));
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }

        if (! empty($filters['plant_id'])) {
            $query->where('plant_id', $filters['plant_id']);
        }

        if (! empty($filters['in_stock'])) {
            $query->where('stock', '>', 0);
        }

        if ($this->hasOrigin($filters)) {
            $this->applyDatabaseRadius($query, $filters);
        }

        if (! empty($filters['sun_requirement']) || ! empty($filters['zone'])) {
            $query->whereHas('plant', function ($q) use ($filters) {
                if (! empty($filters['sun_requirement'])) {
                    $q->where('sun_requirement', $filters['sun_requirement']);
                }
                if (! empty($filters['zone'])) {
                    $q->where('min_zone', '<=', (int) $filters['zone'])
                        ->where('max_zone', '>=', (int) $filters['zone']);
                }
            });
        }

        $total = (clone $query)->count();

        match (true) {
            ($filters['sort'] ?? null) === 'distance' && $this->hasOrigin($filters) => $query->orderBy('distance_km'),
            ($filters['sort'] ?? null) === 'rating_desc' => $query->orderByDesc('reviews_avg_rating'),
            default => $query->orderByDesc('created_at'),
        };

        $results = $query->forPage($page, $perPage)->get();

        return [
            'source' => 'database',
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'results' => $results,
            'facets' => [
                'category' => [],
                'sun_requirement' => [],
            ],
        ];
    }

    public function ensureIndexExists(): void
    {
        if ($this->client()->indices()->exists(['index' => $this->index()])->asBool()) {
            return;
        }

        $this->client()->indices()->create([
            'index' => $this->index(),
            'body' => [
                'mappings' => [
                    'properties' => [
                        'title' => ['type' => 'text'],
                        'description' => ['type' => 'text'],
                        'category' => ['type' => 'keyword'],
                        'sun_requirement' => ['type' => 'keyword'],
                        'plant_type' => ['type' => 'keyword'],
                        'min_zone' => ['type' => 'integer'],
                        'max_zone' => ['type' => 'integer'],
                        'stock' => ['type' => 'integer'],
                        'rating_average' => ['type' => 'float'],
                        'reviews_count' => ['type' => 'integer'],
                        'is_active' => ['type' => 'boolean'],
                        'seller_id' => ['type' => 'integer'],
                        'seller_location' => ['type' => 'geo_point'],
                        'created_at' => ['type' => 'date'],
                    ],
                ],
            ],
        ]);
    }

    public function indexProduct(Product $product): void
    {
        try {
            $this->client()->index([
                'index' => $this->index(),
                'id' => (string) $product->id,
                'body' => $product->toSearchArray(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to index product in Elasticsearch.', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleteProduct(int $productId): void
    {
        try {
            $this->client()->delete(['index' => $this->index(), 'id' => (string) $productId]);
        } catch (Throwable $e) {
            Log::warning('Failed to delete product from Elasticsearch.', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bulk-reindex every product. Returns the number of products indexed.
     */
    public function reindexAll(?callable $onChunk = null): int
    {
        $this->ensureIndexExists();

        $indexed = 0;

        Product::with(['seller', 'plant'])->chunkById(500, function ($products) use (&$indexed, $onChunk) {
            $body = [];

            foreach ($products as $product) {
                $body[] = ['index' => ['_index' => $this->index(), '_id' => (string) $product->id]];
                $body[] = $product->toSearchArray();
            }

            if ($body) {
                $this->client()->bulk(['body' => $body]);
            }

            $indexed += $products->count();
            if ($onChunk) {
                $onChunk($indexed);
            }
        });

        return $indexed;
    }
}
