<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Connection
    |--------------------------------------------------------------------------
    |
    | Search is powered by Elasticsearch when a host is configured. If none is
    | set, or the cluster can't be reached, ProductSearchService falls back to
    | a plain MySQL query so the app keeps working with fewer facets. That is
    | what makes it deployable without paying for a managed cluster.
    |
    */

    // Empty entries are stripped: an unset ELASTICSEARCH_HOST must leave no
    // hosts at all rather than a meaningless "http://", so the search service
    // falls back to the database instead of failing to build a client.
    'hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ELASTICSEARCH_HOST', 'http://127.0.0.1:9200'))
    ))),

    'indices' => [
        'products' => env('ELASTICSEARCH_PRODUCTS_INDEX', 'products'),
    ],

];
