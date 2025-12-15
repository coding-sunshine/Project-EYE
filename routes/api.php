<?php

use App\Http\Controllers\Api\BatchUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Batch Upload API Routes
Route::prefix('batch-upload')->group(function () {
    Route::post('/', [BatchUploadController::class, 'upload']);
    Route::get('/status/{batchId}', [BatchUploadController::class, 'status']);
    Route::get('/recent', [BatchUploadController::class, 'recent']);
    Route::delete('/{batchId}', [BatchUploadController::class, 'cancel']);
});
