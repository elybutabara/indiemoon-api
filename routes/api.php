<?php

use App\Http\Controllers\CoverTemplateController;
use App\Http\Controllers\IllustrationController;
use App\Http\Controllers\SpineController;
use Illuminate\Support\Facades\Route;

Route::post('/spine/calculate', [SpineController::class, 'calculate']);
Route::post('/cover/template', [CoverTemplateController::class, 'generate']);
Route::post('/illustrations/generate', [IllustrationController::class, 'generate']);
