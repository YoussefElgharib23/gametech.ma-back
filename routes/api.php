<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingSectionController;
use App\Http\Controllers\ProductController as PublicProductController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Dashboard\ProductController;

Route::post('/uploads/preview', [UploadController::class, 'storePreview']);

// Public storefront: product by slug (for /products/[slug] page)
Route::get('/products/{slug}', [PublicProductController::class, 'show']);

// Aggregated homepage data
Route::get('/home', [HomeController::class, 'index']);

// Header product search
Route::get('/search/products', [HomeController::class, 'searchProducts']);

// Archive pages by entity type & slug
Route::get('/archive/{entity_type}/{entity_slug}', [HomeController::class, 'archive']);

// Nav: categories with subcategories
Route::get('/categories/with-children', [HomeController::class, 'categoriesWithChildren']);

Route::get('/sliders', [SliderController::class, 'index']);
Route::put('/sliders/bulk', [SliderController::class, 'bulkUpdate']);

Route::apiResource('categories', CategoryController::class);
Route::apiResource('subcategories', SubcategoryController::class);
Route::apiResource('brands', BrandController::class);

Route::put('/products/bulk/status', [ProductController::class, 'bulkUpdateStatus']);
Route::put('/products/bulk/featured', [ProductController::class, 'bulkUpdateFeatured']);

Route::get('/landing-sections', [LandingSectionController::class, 'index']);
Route::get('/landing-sections/{key}', [LandingSectionController::class, 'show']);
Route::put('/landing-sections/{key}', [LandingSectionController::class, 'update']);


Route::group(['prefix' => 'dashboard'], function () {
    Route::apiResource('products', ProductController::class);
});
