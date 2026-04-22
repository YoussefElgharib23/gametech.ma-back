<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use Throwable;

class ImportCategoriesFromJsonCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'categories:import
                            {--path= : Path to JSON (default: database/data/categories.json)}';

    /**
     * @var string
     */
    protected $description = 'Truncate categories, groups, and subcategories, then import from JSON (slugs from model hooks).';

    public function handle(): int
    {
        $path = $this->resolvePath();

        if (! is_readable($path)) {
            $this->error("Cannot read file: {$path}");

            return self::FAILURE;
        }

        try {
            /** @var array<int, mixed> $tree */
            $tree = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Invalid JSON: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($tree)) {
            $this->error('Invalid JSON: root must be an array.');

            return self::FAILURE;
        }

        $this->truncateCategoryTables();

        $skipped = 0;

        DB::beginTransaction();

        try {
            foreach ($tree as $position => $categoryPayload) {
                if (! is_array($categoryPayload)) {
                    $skipped++;

                    continue;
                }

                $cName = trim((string) ($categoryPayload['name'] ?? ''));
                if ($cName === '') {
                    $this->warn('Skipping category entry without name.');
                    $skipped++;

                    continue;
                }

                $category = Category::query()->create([
                    'name' => $cName,
                    'image' => null,
                    'icon' => $this->normalizeIconURL($cName),
                    'status' => 'active',
                    'position' => (int) $position,
                ]);

                $groups = $categoryPayload['groups'] ?? [];
                if (! is_array($groups)) {
                    $groups = [];
                }

                foreach ($groups as $gPos => $groupPayload) {
                    if (! is_array($groupPayload)) {
                        $skipped++;

                        continue;
                    }

                    $gName = trim((string) ($groupPayload['name'] ?? ''));
                    if ($gName === '') {
                        $skipped++;

                        continue;
                    }

                    $group = CategoryGroup::query()->create([
                        'category_id' => $category->id,
                        'name' => $gName,
                        'icon' => $this->normalizeIconURL($groupPayload['icon'] ?? null),
                        'status' => 'active',
                        'position' => (int) $gPos,
                    ]);

                    $subs = $groupPayload['subcategories'] ?? [];
                    if (! is_array($subs)) {
                        $subs = [];
                    }

                    foreach ($subs as $sPos => $subPayload) {
                        if (! is_array($subPayload)) {
                            $skipped++;

                            continue;
                        }

                        $sName = trim((string) ($subPayload['name'] ?? ''));
                        if ($sName === '') {
                            $skipped++;

                            continue;
                        }

                        Subcategory::query()->create([
                            'category_group_id' => $group->id,
                            'name' => $sName,
                            'status' => 'active',
                            'position' => (int) $sPos,
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Import finished. Table totals — categories: %d, groups: %d, subcategories: %d. Skipped invalid fragments: %d.',
            Category::query()->count(),
            CategoryGroup::query()->count(),
            Subcategory::query()->count(),
            $skipped,
        ));

        return self::SUCCESS;
    }

    private function truncateCategoryTables(): void
    {
        $this->warn('Truncating subcategories, category_groups, and categories; nulling product category links.');

        Product::query()->update([
            'category_id' => null,
            'subcategory_id' => null,
            'category_group_id' => null,
        ]);

        Schema::disableForeignKeyConstraints();

        try {
            Subcategory::query()->truncate();
            CategoryGroup::query()->truncate();
            Category::query()->truncate();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function resolvePath(): string
    {
        $option = $this->option('path');
        if (is_string($option) && $option !== '') {
            if (str_starts_with($option, '/')) {
                return $option;
            }

            return base_path($option);
        }

        return database_path('data/categories.json');
    }

    private function normalizeIconURL(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $value = strtoupper($value);

        return config('app.url') . "/storage/categories/icons/" . $value . ".png";
    }
}
