<?php

use App\Http\Controllers\Api\TranslationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/translations/export/{locale}', [TranslationController::class, 'export']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('translations', TranslationController::class)->except(['destroy']);
});
