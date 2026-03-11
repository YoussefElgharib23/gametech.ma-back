<?php

namespace App\Console\Commands;

use App\Models\Brand;
use Illuminate\Console\Command;

class MigrateBrandsFromJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brands:migrate-from-json
                            {--path= : Path to the JSON file (default: storage/brands.json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate brands from a JSON file (name, slug, path) into the brands table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->option('path') ?? storage_path('brands.json');

        if (! is_readable($path)) {
            $this->error("File not found or not readable: {$path}");

            return self::FAILURE;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: ' . json_last_error_msg());

            return self::FAILURE;
        }

        if (! is_array($data)) {
            $this->error('JSON root must be an array.');

            return self::FAILURE;
        }

        $count = 0;
        foreach ($data as $item) {
            $name = $item['name'] ?? null;
            $slug = $item['slug'] ?? null;
            $pathValue = $item['path'] ?? null;

            if (empty($name) || empty($slug)) {
                $this->warn('Skipping item with missing name or slug: ' . json_encode($item));

                continue;
            }

            Brand::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'image' => $pathValue,
                    'status' => 'active',
                ]
            );
            $count++;
        }

        $this->info("Migrated {$count} brand(s) from {$path}.");

        return self::SUCCESS;
    }
}
