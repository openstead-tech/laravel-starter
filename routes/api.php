<?php

use App\Http\Controllers\AcceptanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AcceptanceController::class, 'index']);
Route::get('/health', [AcceptanceController::class, 'health']);
Route::middleware('acceptance')->prefix('acceptance')->group(function (): void {
    Route::post('/records', [AcceptanceController::class, 'store']);
    Route::get('/records/{id}', [AcceptanceController::class, 'show'])->where('id', '[a-f0-9]{32}');
    Route::patch('/records/{id}', [AcceptanceController::class, 'update'])->where('id', '[a-f0-9]{32}');
    Route::delete('/records/{id}', [AcceptanceController::class, 'destroy'])->where('id', '[a-f0-9]{32}');
});
