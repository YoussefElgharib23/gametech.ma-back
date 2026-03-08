<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LandingSectionController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/uploads/preview', [UploadController::class, 'storePreview']);

Route::get('/sliders', [SliderController::class, 'index']);
Route::put('/sliders/bulk', [SliderController::class, 'bulkUpdate']);

Route::apiResource('categories', CategoryController::class);

Route::get('/landing-sections', [LandingSectionController::class, 'index']);
Route::get('/landing-sections/{key}', [LandingSectionController::class, 'show']);
Route::put('/landing-sections/{key}', [LandingSectionController::class, 'update']);

