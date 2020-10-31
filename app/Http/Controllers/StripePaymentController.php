<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;


class StripePaymentController extends Controller
{
    private $paymentGateway;

    /**
     * Gives view page of stripe
     * @param $appId
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function create($appId)
    {
        return view('stripe', compact('appId'));
    }

    /**
     * Creates a checkout session
     * @param $appId
     * @param PaymentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCheckoutSession($appId, PaymentRequest $request)
    {
        try {
            $this->setPaymentGateway($appId);

            Stripe::setApiKey($this->paymentGateway->app_secret);

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [ 'name' => 'Donation'],
                        'unit_amount' => $request->input('amount') * 100,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('payment.stripe.status', $appId).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payment.stripe.status', $appId).'?session_id={CHECKOUT_SESSION_ID}',
            ]);

            $this->paymentGateway->payments()->create(['amount'=> $request->input('amount'),
                'status'=> 'PENDING', 'transaction_id'=> $session->id]);

        } catch (ApiErrorException $e) {
            return response()->json(['id'=> $e->getMessage()], 500);
        } catch (\Exception $e) {
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
            $this->setPaymentGateway($appId);

            $session = Session::retrieve($request->input('session_id'));

            if ($session->payment_status === 'paid') {
                // update payment detail for success
                // whenever checkout is clicked, make a payment entry with the details
                $this->paymentGateway->payments()->where('transaction_id', $request->input('session_id'))->update(['status'=> 'SUCCESS']);
                return redirect(route('payment.stripe.view', $appId))->with('success', 'Payment successfully done');
            } else {
                $this->paymentGateway->payments()->where('transaction_id', $request->input('session_id'))->update(['status'=> 'FAILED']);
                return redirect(route('payment.stripe.view', $appId))->with('error', 'Payment failed');
            }

        } catch (ApiErrorException $e) {
            return redirect(route('payment.stripe.view', $appId))->with('error', $e->getMessage());
        } catch (\Exception $e){
            return redirect(route('payment.stripe.view', $appId))->with('error', 'Some error encountered');
        }
    }

    /**
     * Sets payment gateway as stripe and stripe's API key
     * @param $appId
     * @throws \Exception
     */
    private function setPaymentGateway($appId)
    {
        // keeping $appId in a separate If to allow routes without appId too
        if($appId){

            if(!($this->paymentGateway = PaymentGateway::where('app_id', $appId)->first())){
                throw new \Exception('Payment gateway with give App Id is not found');
            }

            Stripe::setApiKey($this->paymentGateway->app_secret);
        }
    }
}
