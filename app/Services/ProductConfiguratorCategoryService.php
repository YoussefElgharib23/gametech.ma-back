<?php

namespace App\Services;

use App\Classes\OpenAI;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ProductConfiguratorCategoryService
{
    /**
     * @return array{
     *   updated: int,
     *   skipped: int,
     *   failed: int,
     *   results: list<array{product_id: int, configurator_category: string|null}>
     * }
     */
    public function setLatestConfiguratorCategories(int $batchSize = 10, bool $dryRun = false, ?string $model = null): array
    {
        /** @var EloquentCollection<int, Product> $products */
        $products = Product::query()
            ->whereNull('configurator_category_classified_at')
            ->latest('id')
            ->limit($batchSize)
            ->get(['id', 'title', 'configurator_category', 'configurator_category_classified_at']);

        if ($products->isEmpty()) {
            return [
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'results' => [],
            ];
        }

        $allowed = $this->allowedConfiguratorCategories();
        $schema = $this->responseSchema($allowed);
        $messages = $this->buildMessages($allowed, $products);

        $response = OpenAI::chatWithStructuredOutput(
            messages: $messages,
            schema: $schema,
            schemaName: 'product_configurator_category',
            model: $model
        );

        if ($response->failed()) {
            return [
                'updated' => 0,
                'skipped' => 0,
                'failed' => $products->count(),
                'results' => [],
            ];
        }

        $decoded = $this->extractStructuredContent($response->json());
        if (! is_array($decoded) || ! isset($decoded['results']) || ! is_array($decoded['results'])) {
            return [
                'updated' => 0,
                'skipped' => 0,
                'failed' => $products->count(),
                'results' => [],
            ];
        }

        $normalized = $this->normalizeResults($decoded['results'], $allowed);

        $productsById = $products->keyBy('id');
        $updated = 0;
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

            if ($product->configurator_category_classified_at !== null) {
                $skipped++;

                continue;
            }

            $value = $row['configurator_category'] ?? null;

            if (! $dryRun) {
                $product->forceFill([
                    'configurator_category' => $value,
                    'configurator_category_classified_at' => now(),
                ])->save();
            }

            $updated++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'results' => $normalized,
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedConfiguratorCategories(): array
    {
        return [
            'Processeur',
            'Cpu cooler',
            'Carte mère',
            'Mémoires ram',
            'Carte graphique',
            'Ssd',
            'Hdd',
            'Boitier gamer',
            'Alimentation pc (psu)',
            'Souris',
            'Clavier',
            'Casque',
            'Microphone',
            'Combo',
            'Ecran pc',
            'Enceintes pc',
            'Webcams',
            'Tapis souris',
        ];
    }

    /**
     * @param  list<string>  $allowed
     * @param  EloquentCollection<int, Product>  $products
     * @return list<array{role: string, content: string}>
     */
    protected function buildMessages(array $allowed, EloquentCollection $products): array
    {
        $productsPayload = $products->map(fn (Product $p) => [
            'id' => $p->id,
            'title' => (string) $p->title,
        ])->values()->all();

        $system = <<<'SYS'
You are a product "configurator category" classifier.

You will receive a list of allowed configurator categories (strings) and a list of products (id + title).

Rules:
- Choose EXACTLY one string from the allowed list if it clearly matches the product.
- If the product does NOT match any allowed category, return null (not an empty string).
- Never invent new categories and never return a near-match string.
SYS;

        $user = "ALLOWED_CATEGORIES_JSON:\n".json_encode(['allowed' => $allowed], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ."\n\nPRODUCTS_JSON:\n".json_encode(['products' => $productsPayload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    protected function responseSchema(array $allowed): array
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
                        'required' => ['product_id', 'configurator_category'],
                        'properties' => [
                            'product_id' => ['type' => 'integer'],
                            'configurator_category' => [
                                'type' => ['string', 'null'],
                                'enum' => array_values(array_merge($allowed, [null])),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
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
     * @param  list<string>  $allowed
     * @return list<array{product_id: int, configurator_category: string|null}>
     */
    protected function normalizeResults(array $results, array $allowed): array
    {
        $allowedMap = array_fill_keys($allowed, true);
        $out = [];

        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }

            $productId = (int) ($row['product_id'] ?? 0);
            $value = $row['configurator_category'] ?? null;

            if ($value === null) {
                $out[] = [
                    'product_id' => $productId,
                    'configurator_category' => null,
                ];

                continue;
            }

            if (! is_string($value) || ! isset($allowedMap[$value])) {
                $value = null;
            }

            $out[] = [
                'product_id' => $productId,
                'configurator_category' => $value,
            ];
        }

        return $out;
    }
}
