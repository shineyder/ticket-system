<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return new JsonResponse(['status' => 'ok']);
});
