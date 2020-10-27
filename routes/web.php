<?php

use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\StripePaymentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


// passing either mysql Id in the url and based on that querying
Route::get('stripe', [StripePaymentController::class, 'index']);
Route::post('payment-process', [StripePaymentController::class, 'process']);

Route::get('dashboard', [PaymentGatewayController::class, 'index']);
Route::post('payment-gateway', [PaymentGatewayController::class, 'store'])->name('payment-gateway');
