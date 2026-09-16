<?php

use App\Http\Controllers\Api\RoyaltyController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/releases/{release}/royalties/calculate',
    [RoyaltyController::class, 'calculate']
);
