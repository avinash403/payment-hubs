<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\Webhook;


class StripePaymentController extends Controller
{
    private $paymentGateway;

    const PRODUCT_NAME = 'Hiba-box';

    /**
     * Gives view page of stripe
     * @param $appId
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function create($appId)
    {
        $this->setPaymentGateway($appId);
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

            if($request->input('is_recurring')){
                $session = $this->createMonthlyPayment($appId, $request->input('amount'));
            } else {
                $session = $this->createOneTimePayment($appId, $request->input('amount'));
            }

            $this->paymentGateway->payments()->create(['amount'=> $request->input('amount'),
                'status'=> 'PENDING', 'session_id'=> $session->id]);

        } catch (ApiErrorException $e) {
            return response()->json($e->getMessage(), 500);
        } catch (\Exception $e) {
            return response()->json('Something went wrong', 500);
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

            $customer = Customer::retrieve($session->customer);

            if ($session->payment_status === 'paid') {
                // update payment detail for success
                // whenever checkout is clicked, make a payment entry with the details
                $this->paymentGateway->payments()->where('session_id', $request->input('session_id'))
                    ->update(['status'=> 'SUCCESS', 'transaction_id'=> $session->payment_intent,
                        'customer_email'=> $customer->email]);

                return redirect(route('payment.stripe.view', $appId))->with('success', 'Thank you for your valuable contribution!');
            } else {
                $this->paymentGateway->payments()->where('session_id', $request->input('session_id'))
                    ->update(['status'=> 'FAILED', 'transaction_id'=> $session->payment_intent,
                        'customer_email'=> $customer->email]);

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


    /**
     * Stripe updates recurring payment here in the backgrounf
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function webhook(Request $request)
    {
        // Parse the message body (and check the signature if possible)
        $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET');

        if ($webhookSecret) {
            try {
                $event = Webhook::constructEvent(
                    $request->getContent(),
                    $request->header('stripe-signature'),
                    $webhookSecret
                );

            } catch (\Exception $e) {
                return response()->json($e->getMessage(), 403);
            }
        } else {
            $event = $request->all();
        }
        $type = $event['type'];
        $object = $event['data']['object'];

        // create a new payment with date and source
        // need appId

        // Handle the event
        // Review important events for Billing webhooks
        // https://stripe.com/docs/billing/webhooks
        // Remove comment to see the various objects sent for this sample
        switch ($type) {
            case 'invoice.paid':
                // The status of the invoice will show up as paid. Store the status in your
                // database to reference when a user accesses your service to avoid hitting rate
                // limits.
                return 'success';
            case 'invoice.payment_failed':
                // If the payment fails or the customer does not have a valid payment method,
                // an invoice.payment_failed event is sent, the subscription becomes past_due.
                // Use this webhook to notify your user that their payment has
                // failed and to retrieve new card details.
                return 'failed';
            case 'customer.subscription.deleted':
                // handle subscription cancelled automatically based
                // upon your subscription settings. Or if the user
                // cancels it.
                return 'deleted';
            default:
                // Unhandled event type
        }

        return response()->json('success', 403);
    }

    /**
     * Makes a one time payment
     * @param $appId
     * @param $amount
     * @return Session
     * @throws ApiErrorException
     */
    private function createOneTimePayment($appId, $amount)
    {
        return Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [ 'name' => 'Donation'],
                    'unit_amount' => $amount * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('payment.stripe.status', $appId).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('payment.stripe.status', $appId).'?session_id={CHECKOUT_SESSION_ID}',
        ]);
    }

    /**
     * Makes a monthly payment
     * @param $appId
     * @param $amount
     * @return Session
     * @throws ApiErrorException
     */
    private function createMonthlyPayment($appId, $amount)
    {
        // add check if this price exists
        $price = $this->getPrice($amount, 'USD');

        return Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price'=> $price->id,
                'quantity'=>1
            ]],
            'mode' => 'subscription',
            'success_url' => route('payment.stripe.status', $appId).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('payment.stripe.status', $appId).'?session_id={CHECKOUT_SESSION_ID}',
        ]);
    }

    /**
     * Gives price object
     * @internal If price with same amount doesn't exist, it will create one in default product
     * @param $amount
     * @param $currency
     * @return mixed|Price
     * @throws ApiErrorException
     */
    private function getPrice($amount, $currency)
    {
        $productId = $this->getProduct();

        // amount is given in cents or paisa. Has to be converted into dollar or rupees
        $amount = $amount * 100;
        foreach (Price::all() as $price){
            // if price exists with passed product ID, we return the same else create a new price
            if($price->product === $productId && $price->unit_amount === $amount
                && $price->currency === $currency) {
                return $price;
            }
        }

        return Price::create(['currency'=> 'usd', 'product'=>$productId,
            'recurring'=>['interval'=>'month', 'interval_count'=> 1], 'unit_amount'=> $amount]);
    }

    /**
     * Gives product with default name
     * @return mixed|Product
     * @throws ApiErrorException
     */
    private function getProduct()
    {
        foreach (Product::all() as $product){
            if($product->name === self::PRODUCT_NAME){
                return $product;
            }
        }
        return Product::create(['name'=> self::PRODUCT_NAME]);
    }
}
