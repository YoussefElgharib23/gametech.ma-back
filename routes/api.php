<?php

use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Dashboard\CustomerController as DashboardCustomerController;
use App\Http\Controllers\Dashboard\OverviewController as DashboardOverviewController;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\StoreSettingController as DashboardStoreSettingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingSectionController;
use App\Http\Controllers\ProductController as PublicProductController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\VisitorController;
use Illuminate\Support\Facades\Route;

Route::post('/uploads/preview', [UploadController::class, 'storePreview']);

// Dashboard auth (Sanctum token)
Route::post('/auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/auth/logout', [AuthController::class, 'logout']);

// Public storefront: product by slug (for /products/[slug] page)
Route::get('/products/{slug}', [PublicProductController::class, 'show']);

// Aggregated homepage data
Route::get('/home', [HomeController::class, 'index']);

// Header product search
Route::get('/search/products', [HomeController::class, 'searchProducts']);

// Configurator: products by configurator_category
Route::get('/configurator/products', [HomeController::class, 'configuratorProducts']);
Route::post('/visit', [VisitorController::class, 'visit']);

Route::middleware('auth:visitor')->group(function () {
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{itemId}/increment', [CartController::class, 'incrementItem']);
    Route::patch('/cart/items/{itemId}/decrement', [CartController::class, 'decrementItem']);
    Route::patch('/cart/items/{itemId}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);
    Route::post('/checkout', CheckoutController::class);
});

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
    Route::get('overview', DashboardOverviewController::class);
    Route::apiResource('products', ProductController::class);
    Route::get('customers', [DashboardCustomerController::class, 'index']);
    Route::get('customers/{id}/orders', [DashboardCustomerController::class, 'orders']);

    // Store settings (key/value)
    Route::get('store-settings', [DashboardStoreSettingController::class, 'index']);
    Route::put('store-settings', [DashboardStoreSettingController::class, 'upsert']);
});

// Admin routes for order management
Route::group(['prefix' => 'admin'], function () {
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/statistics', [AdminOrderController::class, 'statistics']);
    Route::get('/orders/{id}/confirm-preview', [AdminOrderController::class, 'confirmPreview']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::delete('/orders/{id}', [AdminOrderController::class, 'destroy']);
    Route::patch('/orders/{id}/details', [AdminOrderController::class, 'updateDetails']);
    Route::patch('/orders/{orderId}/items/{itemId}', [AdminOrderController::class, 'updateItemQuantity']);
    Route::post('/orders/{orderId}/items', [AdminOrderController::class, 'addItem']);
    Route::delete('/orders/{orderId}/items/{itemId}', [AdminOrderController::class, 'removeItem']);
});
