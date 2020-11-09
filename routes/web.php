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
Route::post('refund/{paymentId}', [PaymentController::class, 'refund'])->name('payment.refund');

Route::post('donate', [PaymentController::class, 'donate'])->name('payments.donate');


Route::get('stripe/{appId}', [StripePaymentController::class, 'create'])->name('payment.stripe.view');
Route::post('stripe/{appId}/checkout-session', [StripePaymentController::class, 'createCheckoutSession'])->name('payment.stripe.session');
Route::get('stripe/{appId}/payment-status', [StripePaymentController::class, 'paymentStatus'])->name('payment.stripe.status');
Route::post('stripe/{appId}/webhook', [StripePaymentController::class, 'webhookListener'])->name('payment.stripe.webhook-listener');

Route::get('paypal/{appId}', [PaypalPaymentController::class, 'create'])->name('payment.paypal.view');
Route::post('paypal/{appId}', [PaypalPaymentController::class, 'paymentOptions'])->name('payment.paypal.options');
Route::post('paypal/{appId}/webhook', [PaypalPaymentController::class, 'webhookListener'])->name('payment.paypal.webhook-listener');

Route::get('payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('payment/failed', [PaymentController::class, 'failed'])->name('payment.failed');
