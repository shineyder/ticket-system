<?php

use App\Infrastructure\Http\Controllers\TicketController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return new JsonResponse(['status' => 'ok']);
});

Route::controller(TicketController::class)->group(function () {
    Route::post('/ticket', 'store');
    Route::put('/ticket/{id}', 'resolve');
    Route::get('/ticket/{id}', 'show');
    Route::get('/', 'all');
});
