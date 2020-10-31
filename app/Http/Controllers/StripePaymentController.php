<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;


class StripePaymentController extends Controller
{

    /**
     * Gives view page of stripe
     * @param $appId
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function stripe($appId)
    {
        return view('stripe', compact('appId'));
    }

    /**
     * Creates a checkout session
     * @param $appId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCheckoutSession($appId, Request $request)
    {
        try {
            $appSecret = PaymentGateway::where('app_id', $appId)->value('app_secret');

            Stripe::setApiKey($appSecret);

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Donation',
                        ],
                        'unit_amount' => $request->input('amount') * 100,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('payment.stripe.status', $appId).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payment.stripe.status', $appId).'?session_id={CHECKOUT_SESSION_ID}',
            ]);

        } catch (ApiErrorException $e) {
            return response()->json(['id'=> $e->getMessage()], 500);
        }
        return response()->json(['id'=> $session->id]);
    }

    /**
     * Updates the payment status
     * @param $appId
     * @param Request $request
     */
    public function paymentStatus($appId, Request $request)
    {
        try {
            $appSecret = PaymentGateway::where('app_id', $appId)->value('app_secret');

            Stripe::setApiKey($appSecret);

            $session = Session::retrieve($request->input('session_id'));

            if ($session->payment_status === 'paid') {
                // update payment detail for success
                // whenever checkout is clicked, make a payment entry with the details
                return redirect(route('payment.stripe.view', $appId))->with('success', 'Payment successfully done');
            } else {

                // update payment status for failure
                return redirect(route('payment.stripe.view', $appId))->with('error', 'Payment failed');
            }

//            dd($session, $request->input('session_id'), $session->payment_status); // 'paid'

        } catch (ApiErrorException $e) {
            return redirect(route('payment.stripe.view', $appId))->with('error', $e->getMessage());
        } catch (\Exception $e){
            return redirect(route('payment.stripe.view', $appId))->with('error', 'Some error encountered');
        }
    }
}
