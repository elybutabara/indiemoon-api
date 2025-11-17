<?php

use App\Http\Controllers\SpineController;
use Illuminate\Support\Facades\Route;

Route::post('/spine/calculate', [SpineController::class, 'calculate']);
