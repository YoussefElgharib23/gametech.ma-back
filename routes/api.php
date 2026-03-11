<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LandingSectionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/uploads/preview', [UploadController::class, 'storePreview']);

Route::get('/sliders', [SliderController::class, 'index']);
Route::put('/sliders/bulk', [SliderController::class, 'bulkUpdate']);

Route::apiResource('categories', CategoryController::class);
Route::apiResource('subcategories', SubcategoryController::class);
Route::apiResource('brands', BrandController::class);
Route::apiResource('products', ProductController::class);

Route::put('/products/bulk/status', [ProductController::class, 'bulkUpdateStatus']);
Route::put('/products/bulk/featured', [ProductController::class, 'bulkUpdateFeatured']);

Route::get('/landing-sections', [LandingSectionController::class, 'index']);
Route::get('/landing-sections/{key}', [LandingSectionController::class, 'show']);
Route::put('/landing-sections/{key}', [LandingSectionController::class, 'update']);

