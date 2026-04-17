<?php

namespace App\Console\Commands;

use App\Services\ProductConfiguratorCategoryService;
use Illuminate\Console\Command;

class CategorizeProductsConfiguratorAiCommand extends Command
{
    public function __construct(
        protected ProductConfiguratorCategoryService $configuratorCategoryService,
    ) {
        parent::__construct();
    }

    /**
     * @var string
     */
    protected $signature = 'products:categorize-configurator-ai
                            {--batch=10 : Products per OpenAI request}
                            {--max=0 : Max products to attempt (0 = no limit)}
                            {--dry-run : Do not persist changes}
                            {--model= : Override OpenAI model}';

    /**
     * @var string
     */
    protected $description = 'Set products configurator_category using AI (allowed list only)';

    public function handle(): int
    {
        $batch = max(1, (int) $this->option('batch'));
        $max = max(0, (int) $this->option('max'));
        $dryRun = (bool) $this->option('dry-run');
        $model = $this->option('model');
        $model = is_string($model) && $model !== '' ? $model : null;

        $remaining = $max;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        $this->info('Starting AI configurator_category classification...');

        while (true) {
            $currentBatchSize = $batch;
            if ($max > 0) {
                if ($remaining <= 0) {
                    break;
                }
                $currentBatchSize = min($batch, $remaining);
            }

            $result = $this->configuratorCategoryService->setLatestConfiguratorCategories(
                batchSize: $currentBatchSize,
                dryRun: $dryRun,
                model: $model,
            );

            $totalUpdated += (int) ($result['updated'] ?? 0);
            $totalSkipped += (int) ($result['skipped'] ?? 0);
            $totalFailed += (int) ($result['failed'] ?? 0);

            $processedThisLoop = $currentBatchSize;
            if ($max > 0) {
                $remaining -= $processedThisLoop;
            }

            $this->line(sprintf(
                'Batch done: updated=%d skipped=%d failed=%d',
                (int) ($result['updated'] ?? 0),
                (int) ($result['skipped'] ?? 0),
                (int) ($result['failed'] ?? 0),
            ));

            $results = $result['results'] ?? [];
            if (! is_array($results) || $results === []) {
                break;
            }

            if (($result['updated'] ?? 0) === 0 && ($result['failed'] ?? 0) === 0) {
                break;
            }

            if ($max === 0 && $processedThisLoop < $batch) {
                break;
            }
        }

        $this->info(sprintf(
            'Done. updated=%d skipped=%d failed=%d',
            $totalUpdated,
            $totalSkipped,
            $totalFailed,
        ));

        if ($dryRun) {
            $this->warn('Dry run enabled: no changes were saved.');
        }

        return self::SUCCESS;
    }
}
