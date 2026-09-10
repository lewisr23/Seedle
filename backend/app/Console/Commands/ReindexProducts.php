<?php

namespace App\Console\Commands;

use App\Services\Search\ProductSearchService;
use Illuminate\Console\Command;
use Throwable;

class ReindexProducts extends Command
{
    protected $signature = 'products:reindex';

    protected $description = 'Bulk-reindex all products into Elasticsearch';

    public function handle(ProductSearchService $search): int
    {
        $this->info('Reindexing products into Elasticsearch...');

        try {
            $count = $search->reindexAll(function (int $indexed) {
                $this->output->write('.');
            });
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Could not reach Elasticsearch: '.$e->getMessage());
            $this->line('Is the elasticsearch container running? Try: docker compose up -d elasticsearch');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Indexed {$count} products.");

        return self::SUCCESS;
    }
}
