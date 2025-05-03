<?php

use App\Infrastructure\Http\Controllers\TicketController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return new JsonResponse(['status' => 'ok']);
});

Route::prefix('v1')->group(function () {
    Route::controller(TicketController::class)->prefix('/ticket')->group(function () {
        Route::post('/', 'store')->name('tickets.store');
        Route::put('/{id}', 'resolve')->name('tickets.resolve');
        Route::get('/{id}', 'show')->name('tickets.show');
        Route::get('/', 'all')->name('tickets.index');
    });
});
