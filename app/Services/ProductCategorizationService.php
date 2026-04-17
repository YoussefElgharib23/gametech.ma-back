<?php

namespace App\Services;

use App\Classes\OpenAI;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ProductCategorizationService
{
    /**
     * @return array{
     *   categorized: int,
     *   skipped: int,
     *   failed: int,
     *   results: list<array{
     *     product_id: int,
     *     category_id: int|null,
     *     category_group_id: int|null,
     *     subcategory_id: int|null
     *   }>
     * }
     */
    public function categorizeLatestUncategorized(int $batchSize = 10, bool $dryRun = false, ?string $model = null): array
    {
        /** @var EloquentCollection<int, Product> $products */
        $products = Product::query()
            ->whereNull('category_id')
            ->whereNull('category_group_id')
            ->whereNull('subcategory_id')
            ->latest('id')
            ->limit($batchSize)
            ->get(['id', 'title', 'category_id', 'category_group_id', 'subcategory_id']);

        if ($products->isEmpty()) {
            return [
                'categorized' => 0,
                'skipped' => 0,
                'failed' => 0,
                'results' => [],
            ];
        }

        $taxonomy = $this->buildTaxonomyPayload();
        $schema = $this->responseSchema();
        $messages = $this->buildMessages($taxonomy, $products);

        $response = OpenAI::chatWithStructuredOutput(
            messages: $messages,
            schema: $schema,
            schemaName: 'product_categorization',
            model: $model,
        );

        if ($response->failed()) {
            return [
                'categorized' => 0,
                'skipped' => 0,
                'failed' => $products->count(),
                'results' => [],
            ];
        }

        $decoded = $this->extractStructuredContent($response->json());
        if (! is_array($decoded) || ! isset($decoded['results']) || ! is_array($decoded['results'])) {
            return [
                'categorized' => 0,
                'skipped' => 0,
                'failed' => $products->count(),
                'results' => [],
            ];
        }

        $normalized = $this->normalizeAndValidateResults($decoded['results']);

        $productsById = $products->keyBy('id');
        $categorized = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($normalized as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            /** @var Product|null $product */
            $product = $productsById->get($productId);
            if ($product === null) {
                $failed++;

                continue;
            }

            if (
                $product->category_id !== null
                || $product->category_group_id !== null
                || $product->subcategory_id !== null
            ) {
                $skipped++;

                continue;
            }

            $categoryId = $row['category_id'] ?? null;
            $groupId = $row['category_group_id'] ?? null;
            $subcategoryId = $row['subcategory_id'] ?? null;

            if ($categoryId === null && $groupId === null && $subcategoryId === null) {
                $failed++;

                continue;
            }

            if (! $dryRun) {
                $product->forceFill([
                    'category_id' => $categoryId,
                    'category_group_id' => $groupId,
                    'subcategory_id' => $subcategoryId,
                ])->save();
            }

            $categorized++;
        }

        return [
            'categorized' => $categorized,
            'skipped' => $skipped,
            'failed' => $failed,
            'results' => $normalized,
        ];
    }

    /**
     * @return array{
     *   categories: list<array{
     *     id: int,
     *     name: string,
     *     groups: list<array{
     *       id: int,
     *       name: string,
     *       subcategories: list<array{id: int, name: string}>
     *     }>
     *   }>
     * }
     */
    protected function buildTaxonomyPayload(): array
    {
        $categories = Category::query()
            ->active()
            ->with([
                'groups' => function ($query) {
                    $query->active()
                        ->orderBy('position')
                        ->with([
                            'subcategories' => function ($query) {
                                $query->active()->orderBy('position');
                            },
                        ]);
                },
            ])
            ->orderBy('position')
            ->get(['id', 'name']);

        return [
            'categories' => $categories->map(function (Category $category): array {
                return [
                    'id' => $category->id,
                    'name' => (string) $category->name,
                    'groups' => $category->groups->map(function (CategoryGroup $group): array {
                        return [
                            'id' => $group->id,
                            'name' => (string) $group->name,
                            'subcategories' => $group->subcategories->map(function (Subcategory $sub): array {
                                return [
                                    'id' => $sub->id,
                                    'name' => (string) $sub->name,
                                ];
                            })->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  EloquentCollection<int, Product>  $products
     * @return list<array{role: string, content: string}>
     */
    protected function buildMessages(array $taxonomy, EloquentCollection $products): array
    {
        $productsPayload = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'title' => (string) $p->title,
        ])->values()->all();

        $system = <<<'SYS'
You are a product taxonomy classifier.

You will receive a taxonomy (categories -> groups -> subcategories) with numeric IDs, and a list of products (id + title).

Return the best matching taxonomy IDs for each product:
- Choose the MOST specific match you are confident about.
- It is allowed to return category only (no group, no subcategory).
- It is allowed to return category + group (no subcategory).
- If you return a subcategory_id, it MUST belong to the returned category_group_id and category_id.
- If you return a category_group_id, it MUST belong to the returned category_id.
- Never invent IDs. Only use IDs present in the given taxonomy.
- If none fits, pick the closest HIGH-LEVEL category rather than guessing a wrong group/subcategory.
SYS;

        $user = "TAXONOMY_JSON:\n".json_encode($taxonomy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ."\n\nPRODUCTS_JSON:\n".json_encode(['products' => $productsPayload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['results'],
            'properties' => [
                'results' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['product_id', 'category_id', 'category_group_id', 'subcategory_id'],
                        'properties' => [
                            'product_id' => ['type' => 'integer'],
                            'category_id' => ['type' => ['integer', 'null']],
                            'category_group_id' => ['type' => ['integer', 'null']],
                            'subcategory_id' => ['type' => ['integer', 'null']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Extract the JSON object returned by structured output.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    protected function extractStructuredContent(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $content = data_get($payload, 'choices.0.message.content');
        if (is_array($content)) {
            /** @var array<string, mixed> $content */
            return $content;
        }

        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int, mixed>  $results
     * @return list<array{product_id: int, category_id: int|null, category_group_id: int|null, subcategory_id: int|null}>
     */
    protected function normalizeAndValidateResults(array $results): array
    {
        $categories = Category::query()->pluck('id')->all();
        $categoryIds = array_fill_keys($categories, true);

        /** @var Collection<int, CategoryGroup> $groups */
        $groups = CategoryGroup::query()->get(['id', 'category_id'])->keyBy('id');

        /** @var Collection<int, Subcategory> $subs */
        $subs = Subcategory::query()->get(['id', 'category_group_id', 'category_id'])->keyBy('id');

        $out = [];

        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }

            $productId = (int) ($row['product_id'] ?? 0);
            $categoryId = isset($row['category_id']) ? $this->toIntOrNull($row['category_id']) : null;
            $groupId = isset($row['category_group_id']) ? $this->toIntOrNull($row['category_group_id']) : null;
            $subId = isset($row['subcategory_id']) ? $this->toIntOrNull($row['subcategory_id']) : null;

            if ($subId !== null) {
                $sub = $subs->get($subId);
                if ($sub === null) {
                    $categoryId = null;
                    $groupId = null;
                    $subId = null;
                } else {
                    $groupId = $sub->category_group_id;
                    $categoryId = $sub->category_id;
                }
            } elseif ($groupId !== null) {
                $group = $groups->get($groupId);
                if ($group === null) {
                    $categoryId = null;
                    $groupId = null;
                } else {
                    $categoryId = $group->category_id;
                }
            }

            if ($categoryId !== null && ! isset($categoryIds[$categoryId])) {
                $categoryId = null;
                $groupId = null;
                $subId = null;
            }

            $out[] = [
                'product_id' => $productId,
                'category_id' => $categoryId,
                'category_group_id' => $groupId,
                'subcategory_id' => $subId,
            ];
        }

        return $out;
    }

    protected function toIntOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
