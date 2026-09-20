<?php

namespace App\Providers;

use App\Models\Product;
use App\Observers\ProductObserver;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            return ClientBuilder::create()
                ->setHosts(config('elasticsearch.hosts'))
                ->build();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        $this->registerSqliteMathFunctions();
    }

    /**
     * MySQL has these built in; many SQLite builds, including the one PHP
     * ships with here, are compiled without SQLITE_ENABLE_MATH_FUNCTIONS.
     * Registering them keeps the distance query as a single piece of SQL that
     * runs the same way in tests and in production.
     */
    private function registerSqliteMathFunctions(): void
    {
        Event::listen(function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() !== 'sqlite') {
                return;
            }

            $pdo = $event->connection->getPdo();

            if (! method_exists($pdo, 'sqliteCreateFunction')) {
                return;
            }

            foreach ([
                'sqrt' => fn ($x) => $x === null ? null : sqrt((float) $x),
                'sin' => fn ($x) => $x === null ? null : sin((float) $x),
                'cos' => fn ($x) => $x === null ? null : cos((float) $x),
                'asin' => fn ($x) => $x === null ? null : asin((float) $x),
                'radians' => fn ($x) => $x === null ? null : deg2rad((float) $x),
            ] as $name => $fn) {
                $pdo->sqliteCreateFunction($name, $fn, 1);
            }

            $pdo->sqliteCreateFunction(
                'power',
                fn ($base, $exp) => $base === null ? null : pow((float) $base, (float) $exp),
                2
            );
        });
    }
}
