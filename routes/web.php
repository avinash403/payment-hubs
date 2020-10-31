<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PaypalPaymentController;
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
Route::get('payments', [PaymentController::class, 'index'])->name('payments');

Route::get('stripe/{appId}', [StripePaymentController::class, 'create'])->name('payment.stripe.view');
Route::post('stripe/{appId}/checkout-session', [StripePaymentController::class, 'createCheckoutSession'])->name('payment.stripe.session');
Route::get('stripe/{appId}/payment-status', [StripePaymentController::class, 'paymentStatus'])->name('payment.stripe.status');

Route::get('paypal/{appId}', [PaypalPaymentController::class, 'create'])->name('payment.paypal.view');
Route::post('paypal/{appId}/payment-process', [PaypalPaymentController::class, 'payWithPaypal'])->name('payment.paypal.process');
Route::get('paypal/payment/status', [PaypalPaymentController::class, 'paymentStatus'])->name('paypal.payment.status');
