<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Connection
    |--------------------------------------------------------------------------
    |
    | Product search is powered by Elasticsearch. If the cluster can't be
    | reached, the ProductSearchService falls back to a plain MySQL query
    | so the marketplace keeps working (with fewer facets) during local
    | development without Docker, or if the ES container is down.
    |
    */

    'hosts' => explode(',', env('ELASTICSEARCH_HOST', 'http://127.0.0.1:9200')),

    'indices' => [
        'products' => env('ELASTICSEARCH_PRODUCTS_INDEX', 'products'),
    ],

];
