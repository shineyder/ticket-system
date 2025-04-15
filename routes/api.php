<?php

use App\Infrastructure\Http\Controllers\TicketController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return new JsonResponse(['status' => 'ok']);
});

Route::controller(TicketController::class)->prefix('/ticket')->group(function () {
    Route::post('/', 'store');
    Route::put('/{id}', 'resolve');
    Route::get('/{id}', 'show');
    Route::get('/', 'all');
});
