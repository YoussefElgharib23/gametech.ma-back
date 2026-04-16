<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Product;
use App\Models\Subcategory;
use App\Services\UploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportPcgamerProducts extends Command
{
    /**
     * Create a new command instance.
     */
    public function __construct(
        protected UploadService $uploadService,
    ) {
        parent::__construct();
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:import-pcgamer
                            {--per-page=100 : Number of products per page to request}
                            {--max-pages=0 : Optional limit of pages to import (0 = all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from api.pcgameragadir.ma into local products table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apiKey = '63f4945d921d599f27ae4fdf5bada3f1';
        $baseUrl = 'https://api.pcgameragadir.ma/api/products';

        $perPage = (int) $this->option('per-page');
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $maxPages = (int) $this->option('max-pages');

        $page = 1;
        $imported = 0;

        $this->info('Starting import from PC Gamer Agadir API...');

        while (true) {
            if ($maxPages > 0 && $page > $maxPages) {
                break;
            }

            $timestamp = time();

            $this->line("Fetching page {$page}...");

            $response = Http::withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
            ])->get($baseUrl, [
                'key' => $apiKey,
                'time' => $timestamp,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            if ($response->failed()) {
                $this->error("Request failed for page {$page}: HTTP {$response->status()}");

                return self::FAILURE;
            }

            $payload = $response->json();
            if (! is_array($payload) || ! isset($payload['data']) || ! is_array($payload['data'])) {
                $this->error("Unexpected response structure for page {$page}");

                return self::FAILURE;
            }

            $products = $payload['data'];
            if (count($products) === 0) {
                break;
            }

            foreach ($products as $remote) {
                // Skip soft-deleted products
                if (! empty($remote['deleted_at'])) {
                    continue;
                }

                $localProduct = $this->importSingleProduct($remote);
                if ($localProduct) {
                    $imported++;
                }
            }

            $currentPage = (int) ($payload['current_page'] ?? $page);
            $lastPage = (int) ($payload['last_page'] ?? $page);

            if ($currentPage >= $lastPage) {
                break;
            }

            $page = $currentPage + 1;
        }

        $this->info("Import finished. Imported/updated {$imported} product(s).");

        return self::SUCCESS;
    }

    /**
     * Import a single remote product into the local database.
     *
     * @param  array<string, mixed>  $remote
     */
    protected function importSingleProduct(array $remote): ?Product
    {
        $slug = $remote['slug'] ?? null;
        $name = $remote['name']['fr'] ?? ($remote['name'] ?? null);

        if (empty($slug) || empty($name)) {
            $this->warn('Skipping product with missing slug or name: '.json_encode($remote));

            return null;
        }

        $description = $remote['short_description']['fr'] ?? $remote['description']['fr'] ?? null;

        $priceRaw = (float) ($remote['price'] ?? 0);
        $specialPriceRaw = (float) ($remote['special_price'] ?? 0);

        $price = $priceRaw;
        $compareAt = null;
        if ($specialPriceRaw > 0 && $specialPriceRaw < $priceRaw) {
            $price = $specialPriceRaw;
            $compareAt = $priceRaw;
        }

        $quantity = (int) ($remote['quantity'] ?? 0);
        $isActive = (bool) ($remote['is_active'] ?? false);

        $subCategory = $remote['sub_category'] ?? null;
        $categoryGroupPayload = is_array($subCategory) ? ($subCategory['category_group'] ?? null) : null;
        $categoryData = is_array($categoryGroupPayload) ? ($categoryGroupPayload['category'] ?? null) : null;

        $categoryId = null;
        $categoryGroupId = null;
        $subcategoryId = null;

        if (is_array($categoryData)) {
            $categorySlug = $categoryData['slug'] ?? null;
            $categoryName = $categoryData['name']['fr'] ?? ($categoryData['name'] ?? null);

            if ($categorySlug && $categoryName) {
                $category = Category::firstOrCreate(
                    ['slug' => $categorySlug],
                    [
                        'name' => $categoryName,
                        'status' => 'active',
                        'position' => 0,
                    ],
                );
                $categoryId = $category->id;
            }
        }

        if (is_array($categoryGroupPayload) && $categoryId !== null) {
            $groupSlug = $categoryGroupPayload['slug'] ?? null;
            $groupName = $categoryGroupPayload['name']['fr'] ?? ($categoryGroupPayload['name'] ?? null);

            if ($groupSlug && $groupName) {
                $group = CategoryGroup::firstOrCreate(
                    [
                        'category_id' => $categoryId,
                        'slug' => $groupSlug,
                    ],
                    [
                        'name' => $groupName,
                        'status' => 'active',
                        'position' => 0,
                    ],
                );
                $categoryGroupId = $group->id;
            }
        }

        if ($categoryId !== null && $categoryGroupId === null) {
            $fallback = CategoryGroup::firstOrCreate(
                [
                    'category_id' => $categoryId,
                    'slug' => 'general',
                ],
                [
                    'name' => 'Général',
                    'status' => 'active',
                    'position' => 0,
                ],
            );
            $categoryGroupId = $fallback->id;
        }

        if (is_array($subCategory) && $categoryGroupId !== null) {
            $subcategorySlug = $subCategory['slug'] ?? null;
            $subcategoryName = $subCategory['name']['fr'] ?? ($subCategory['name'] ?? null);

            if ($subcategorySlug && $subcategoryName) {
                $subcategory = Subcategory::firstOrCreate(
                    [
                        'category_group_id' => $categoryGroupId,
                        'slug' => $subcategorySlug,
                    ],
                    [
                        'name' => $subcategoryName,
                        'status' => 'active',
                        'position' => 0,
                    ],
                );
                $subcategoryId = $subcategory->id;
            }
        }

        $brandId = null;
        if (! empty($remote['brand']) && is_array($remote['brand'])) {
            $brandSlug = $remote['brand']['slug'] ?? null;
            $brandName = $remote['brand']['name']['fr'] ?? ($remote['brand']['name'] ?? null);
            $brandImage = $remote['brand']['path'] ?? null;

            if ($brandSlug && $brandName) {
                $brand = Brand::firstOrCreate(
                    ['slug' => $brandSlug],
                    [
                        'name' => $brandName,
                        'image' => $brandImage,
                        'status' => 'active',
                    ],
                );
                $brandId = $brand->id;
            }
        }

        /** @var Product $product */
        try {
            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'sku' => $remote['uid'] ?? $slug,
                    'title' => $name,
                    'short_description' => null,
                    'description' => $description,
                    'category_id' => $categoryId,
                    'category_group_id' => $categoryGroupId,
                    'subcategory_id' => $subcategoryId,
                    'brand_id' => $brandId,
                    'price' => $price,
                    'compare_at_price' => $compareAt,
                    'stock_status' => $quantity > 0 ? 'in_stock' : 'out_of_stock',
                    'stock_quantity' => $quantity,
                    'status' => $isActive ? 'active' : 'inactive',
                    'is_featured' => (bool) ($remote['is_recommended'] ?? false),
                    'position' => 0,
                    'published_at' => $remote['created_at'] ?? null,
                ],
            );
        } catch (\Throwable $e) {
            $this->error('Error importing product '.$slug.': '.$e->getMessage());

            return null;
        }

        // Attach images via UploadService if none exist yet for this product.
        if (! $product->uploads()->exists()) {
            $imageUrls = $this->extractImageUrls($remote);

            foreach ($imageUrls as $index => $url) {
                try {
                    $upload = $this->uploadService->storeFromUrl($url, $product, 'products', 'uploads');
                    $upload->position = $index;
                    $upload->save();
                } catch (\Throwable $e) {
                    $this->warn('Failed to download image "'.$url.'" for product '.$slug.': '.$e->getMessage());
                }
            }
        }

        return $product;
    }

    /**
     * Extract image URLs from the remote product payload.
     *
     * @param  array<string, mixed>  $remote
     * @return list<string>
     */
    protected function extractImageUrls(array $remote): array
    {
        return collect($remote['images'])->pluck('url')->unique()->values()->toArray();
    }
}
