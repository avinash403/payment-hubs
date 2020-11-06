<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\WebhookEndpoint;


class StripePaymentController extends Controller
{
    /**
     * @var PaymentGateway
     */
    private $paymentGateway;

    const PRODUCT_NAME = 'Hiba-box';

    const CURRENCY = 'gbp';

    const CURRENCY_SYMBOL = '£';

    /**
     * Gives view page of stripe
     * @param $appId
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function create($appId)
    {
        $this->setPaymentGateway($appId);

        $currency = self::CURRENCY;
        $currencySymbol = self::CURRENCY_SYMBOL;
        return view('stripe', compact('appId', 'currency', 'currencySymbol'));
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
                $session = $this->createMonthlyPayment($appId, $request->input('amount'), $request->input('currency'));
            } else {
                $session = $this->createOneTimePayment($appId, $request->input('amount'), $request->input('currency'));
            }

            $frequency = $request->input('is_recurring') ? 'monthly': null;

            $this->paymentGateway->payments()->create(['amount'=> $request->input('amount'),
                'currency'=> $request->input('currency'), 'status'=> 'PENDING', 'session_id'=> $session->id, 'frequency'=> $frequency]);

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
                        'customer_email'=> $customer->email, 'customer_name'=> $customer->name]);

                return redirect(route('payment.success'));
//                return redirect(route('payment.stripe.view', $appId))->with('success', 'Thank you for your valuable contribution!');
            } else {
                $this->paymentGateway->payments()->where('session_id', $request->input('session_id'))
                    ->update(['status'=> 'FAILED', 'transaction_id'=> $session->payment_intent,
                        'customer_email'=> $customer->email, 'customer_name'=> $customer->name]);

                return redirect(route('payment.failed'));
//                return redirect(route('payment.stripe.view', $appId))->with('error', 'Payment failed');
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
     * Makes a one time payment
     * @param $appId
     * @param $amount
     * @param $currency
     * @return Session
     * @throws ApiErrorException
     */
    private function createOneTimePayment($appId, $amount, $currency)
    {
        return Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
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
     * @param $currency
     * @return Session
     * @throws ApiErrorException
     */
    private function createMonthlyPayment($appId, $amount, $currency)
    {
        // add check if this price exists
        $price = $this->getPrice($amount, $currency);

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
        $productId = $this->getProduct()->id;

        // amount is given in cents or paisa. Has to be converted into dollar or rupees
        $amount = $amount * 100;
        foreach (Price::all() as $price){
            // if price exists with passed product ID, we return the same else create a new price
            if($price->product === $productId && $price->unit_amount === $amount
                && strtolower($price->currency) === strtolower($currency)) {
                return $price;
            }
        }

        return Price::create(['currency'=> $currency, 'product'=>$productId,
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

    /**
     * @param $appId
     * @param $appSecret
     */
    public function isValidCredentials($appId, $appSecret)
    {
        try {
            $webhook = $this->createWebhook($appId, $appSecret);
            return ['webhook_secret' => $webhook->secret];
        } catch (\Exception $e){
            return false;
        }
    }

    /**
     * Creates webhook
     * @param $appId
     * @param $appSecret
     * @throws ApiErrorException
     */
    private function createWebhook($appId, $appSecret)
    {
        Stripe::setApiKey($appSecret);

        return WebhookEndpoint::create([
            'url'=> route('payment.stripe.webhook-listener', $appId),
            'enabled_events'=> ['invoice.payment_succeeded', 'invoice.payment_failed']
        ]);
    }

    /**
     * Listens for webhook calls and update payments accordingly
     * @param $appId
     * @param Request $request
     */
    public function webhookListener($appId, Request $request)
    {
        try {
            \Log::info('webhook received');

            $this->setPaymentGateway($appId);

            // stripe only worked with raw payload
            $payload = @file_get_contents('php://input');

            \Log::info('before event retrieval');
            \Log::info($request->header('Stripe-Signature'));
            \Log::info($_SERVER['HTTP_STRIPE_SIGNATURE']);
            \Log::info($payload);


            // test
            $event = Webhook::constructEvent($payload, $request->header('Stripe-Signature'), 'whsec_2kbnrTkjr5zMLKmYg4FZ3if632SjNoCg');

            // live
//            $event = Webhook::constructEvent($payload, $request->header('Stripe-Signature'), $this->paymentGateway->webhook_secret);

            \Log::info('webhook verification passed');
            \Log::info(json_encode($request->all()));

            if(in_array($event->type, ['invoice.payment_succeeded', 'invoice.payment_failed'])){

                $data = $event->data;

                $customerId = $data['object']['customer'];

                $paymentIntentId = $data['object']['payment_intent'];

                $amount = $data['object']['amount_paid'];

                $currency = $data['object']['currency'];

                \Log::info('customerId : '.$customerId);
                $customer = Customer::retrieve($customerId);

                $paymentStatus = $event->type === "invoice.payment_succeeded" ? 'SUCCESS' : 'FAILED';

                $this->paymentGateway->payments()->create(['status'=> $paymentStatus, 'transaction_id'=> $paymentIntentId,
                    'customer_email'=> $customer->email, 'customer_name'=> $customer->name, 'frequency'=>'monthly',
                    'amount'=>$amount, 'currency'=>$currency]);

                \Log::info('payment updated successfully');

                return response()->json('payment recorded successfully');
            }

            return response()->json('event ignored');

        } catch (SignatureVerificationException $e){
            \Log::error('signaure failed');
            \Log::error($e->getMessage());
            return response()->json('signature verification failed', 400);
        } catch (\Exception $e){
            \Log::error('error encountered');
            \Log::error($e->getMessage());
            \Log::error($e->getLine());
            \Log::error($e->getFile());
            \Log::error($e->getTraceAsString());
            return response()->json('event ignored');
        }
    }
}
