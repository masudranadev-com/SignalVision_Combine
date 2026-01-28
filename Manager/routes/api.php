<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
// API FORM
Route::controller(\App\Http\Controllers\API\SignalFormatAPIController::class)->prefix("signal-format")->middleware('api_key')->group(function(){
    Route::get('groups', 'groups');
    Route::post('submit-form', 'submitForm');
});

// SignalShot API 
Route::controller(\App\Http\Controllers\API\SignalShotController::class)->prefix("signal-shot")->middleware('api_key')->group(function(){
    Route::get('active-real-positions', 'activeRealPositions');
    Route::get('active-demo-positions', 'activeDemoPositions');
    Route::post('risk-management-reset', 'riskManagementReset');
    Route::get('partial-templates', 'partialTemplates');
});

// SignalShot API 
Route::controller(\App\Http\Controllers\API\NodeAPIController::class)->prefix("node")->middleware('api_key')->group(function(){
    Route::post('notification', 'notification');
    Route::post('ini-trades', 'initialTrades');
});