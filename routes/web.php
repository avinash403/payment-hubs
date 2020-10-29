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
    return redirect(route('dashboard'));
    //return view('welcome');
});

Route::get('dashboard', [PaymentGatewayController::class, 'index'])->name('dashboard');
Route::post('payment-gateway', [PaymentGatewayController::class, 'store'])->name('payment-gateway');
Route::get('widget-code/{appId}', [PaymentGatewayController::class, 'getWidgetCode'])->name('widget-code');

// passing either mysql Id in the url and based on that querying
Route::get('stripe/{appId}', [StripePaymentController::class, 'stripe'])->name('payment.stripe.view');
Route::post('stripe/{appId}/payment-process', [StripePaymentController::class, 'process'])->name('payment.stripe.process');


Route::get('paypal/{appId}', [\App\Http\Controllers\PaypalPaymentController::class, 'paypal'])->name('payment.paypal.view');
Route::post('paypal/{appId}/payment-process', [\App\Http\Controllers\PaypalPaymentController::class, 'payWithPaypal'])->name('payment.paypal.process');
Route::get('paypal/payment/status', [\App\Http\Controllers\PaypalPaymentController::class, 'getPaymentStatus'])->name('paypal.payment.status');
