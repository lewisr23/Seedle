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
 * is unreachable — e.g. running locally without Docker — we degrade to a
 * MySQL LIKE query so the marketplace keeps working, just without facets.
 */
class ProductSearchService
{
    public function __construct(private readonly Client $client) {}

    private function index(): string
    {
        return config('elasticsearch.indices.products');
    }

    /**
     * @param  array{q?: string, category?: string, sun_requirement?: string, zone?: int, min_price?: int, max_price?: int, seller_id?: int, plant_id?: int, in_stock?: bool, sort?: string}  $filters
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

        if (! empty($filters['min_price']) || ! empty($filters['max_price'])) {
            $range = [];
            if (! empty($filters['min_price'])) {
                $range['gte'] = (int) $filters['min_price'];
            }
            if (! empty($filters['max_price'])) {
                $range['lte'] = (int) $filters['max_price'];
            }
            $filter[] = ['range' => ['price_pence' => $range]];
        }

        if (! empty($filters['in_stock'])) {
            $filter[] = ['range' => ['stock' => ['gt' => 0]]];
        }

        $query = empty($must) && empty($filter)
            ? ['match_all' => new \stdClass]
            : ['bool' => array_filter(['must' => $must, 'filter' => $filter])];

        $response = $this->client->search([
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
        return match ($filters['sort'] ?? null) {
            'price_asc' => [['price_pence' => 'asc']],
            'price_desc' => [['price_pence' => 'desc']],
            'rating_desc' => [['rating_average' => ['order' => 'desc', 'missing' => '_last']]],
            default => empty($filters['q']) ? [['created_at' => 'desc']] : ['_score'],
        };
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

        if (! empty($filters['min_price'])) {
            $query->where('price_pence', '>=', (int) $filters['min_price']);
        }

        if (! empty($filters['max_price'])) {
            $query->where('price_pence', '<=', (int) $filters['max_price']);
        }

        if (! empty($filters['in_stock'])) {
            $query->where('stock', '>', 0);
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

        match ($filters['sort'] ?? null) {
            'price_asc' => $query->orderBy('price_pence'),
            'price_desc' => $query->orderByDesc('price_pence'),
            'rating_desc' => $query->orderByDesc('reviews_avg_rating'),
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
        if ($this->client->indices()->exists(['index' => $this->index()])->asBool()) {
            return;
        }

        $this->client->indices()->create([
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
                        'price_pence' => ['type' => 'integer'],
                        'stock' => ['type' => 'integer'],
                        'rating_average' => ['type' => 'float'],
                        'reviews_count' => ['type' => 'integer'],
                        'is_active' => ['type' => 'boolean'],
                        'seller_id' => ['type' => 'integer'],
                        'created_at' => ['type' => 'date'],
                    ],
                ],
            ],
        ]);
    }

    public function indexProduct(Product $product): void
    {
        try {
            $this->client->index([
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
            $this->client->delete(['index' => $this->index(), 'id' => (string) $productId]);
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
                $this->client->bulk(['body' => $body]);
            }

            $indexed += $products->count();
            if ($onChunk) {
                $onChunk($indexed);
            }
        });

        return $indexed;
    }
}
