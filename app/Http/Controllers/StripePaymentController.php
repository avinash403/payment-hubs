<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Models\Payment;
use App\Models\PaymentGateway;
use Dotenv\Util\Str;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Price;
use Stripe\Product;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Subscription;
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
     * @internal The way it works in by creating a payment entry in the database with status as `pending`. It creates a session on stripe for following 2 scenarios
     *  one-time : in this case a straight-forward, redirect happens with a success or a failure. And based on sessionId, we can verify the payment's
     *            transaction_id (payment_intent in stripe's context)
     *  monthly : in this case id of the payment instance is sent as meta_data to stripe's subscription instance while creating a session. When webhook
     *             receives an event for success/failure, it retrieves the payment id and update the transaction_id (payment_intent in stripe's context)
     *
     * @param $appId
     * @param PaymentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCheckoutSession($appId, PaymentRequest $request)
    {
        try {
            $this->setPaymentGateway($appId);

            $frequency = $request->input('is_recurring') ? 'monthly': null;

            $payment = $this->paymentGateway->payments()->create(['amount'=> $request->input('amount'),
                'currency'=> $request->input('currency'), 'status'=> 'PENDING', 'frequency'=> $frequency]);

            if($request->input('is_recurring')){
                $session = $this->createMonthlyPayment($appId, $request->input('amount'), $request->input('currency'), $payment->id);
            } else {
                $session = $this->createOneTimePayment($appId, $request->input('amount'), $request->input('currency'), $payment->id);
            }

            $payment->session_id = $session->id;

            $payment->save();

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

            /**
             * In case of payment with subscription, instead of a payment, invoice is generated and payment happens asynchronously.
             * In that case, we do not have a confirmation on if payment is a success or a failure, so we leave that updation on webhook to handle
             */
            if ($session->payment_status === 'paid') {
                $session->payment_intent && $this->paymentGateway->payments()->where('session_id', $request->input('session_id'))
                    ->update(['status'=> 'COMPLETED', 'transaction_id'=> $session->payment_intent,
                        'customer_email'=> $customer->email, 'customer_name'=> $customer->name]);

                return redirect(route('payment.success'));
            } else {
                $session->payment_intent && $this->paymentGateway->payments()->where('session_id', $request->input('session_id'))
                    ->update(['status'=> 'DENIED', 'transaction_id'=> $session->payment_intent,
                        'customer_email'=> $customer->email, 'customer_name'=> $customer->name]);

                return redirect(route('payment.failed'));
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
    private function createOneTimePayment($appId, $amount, $currency, $paymentId)
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
            'payment_intent_data'=> [
                'metadata'=> [
                    'payment_id'=> $paymentId,
                ]
            ]
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
    private function createMonthlyPayment($appId, $amount, $currency, $paymentId)
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
            'subscription_data'=> [
                'metadata'=> [
                    'payment_id'=> $paymentId,
                ]
            ]
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
     * Initiates a refund
     * @param Payment $payment
     * @throws ApiErrorException
     * @throws \Exception
     */
    public function refund(Payment $payment)
    {
        $appId = $payment->gateway->app_id;

        $this->setPaymentGateway($appId);

        Refund::create(['payment_intent'=> $payment->transaction_id]);

        $payment->status = 'REFUNDED';

        $payment->save();
    }

    /**
     * Listens for webhook calls and update payments accordingly
     * @param $appId
     * @param Request $request
     */
    public function webhookListener($appId, Request $request)
    {
        try {
            \Log::info('stripe webhook received');
            \Log::info(json_encode($request->all()));

            $this->setPaymentGateway($appId);

            if($event = $this->getEventIfValidSignature($request)){
                if(in_array($event->type, ['invoice.payment_succeeded', 'invoice.payment_failed'])){

                    $data = $event->data;

                    $customerId = $data['object']['customer'];

                    $paymentIntentId = $data['object']['payment_intent'];

                    $amount = $data['object']['amount_paid']/100;

                    $currency = $data['object']['currency'];

                    $subscriptionId = $data['object']['subscription'];

                    $customer = Customer::retrieve($customerId);

                    $subscription = Subscription::retrieve($subscriptionId);

                    $paymentId = isset($subscription->metadata['payment_id']) ? $subscription->metadata['payment_id'] : null;

                    $payment = Payment::find($paymentId);

                    $paymentStatus = $this->getStatusOutOfEventName($event->type);

                    /**
                     * Two cases are handled below:
                     * first-time subscription payment: in this case, there will already be a Payment instance in database created during
                     *      checkout process but its transaction_id will be empty. In that case, we pick payment id from meta data and update
                     *      the same entry for its payment_intent id.
                     * recurring payment: in this case, transaction_id of the above payment instance will not be empty, so we create a new entry in
                     *       payment table with different transaction_id
                     */
                    if($payment){
                        if($payment->transaction_id){
                            \Log::info('creating new transaction entry');

                            $this->paymentGateway->payments()->create(['status'=> $paymentStatus,
                                'customer_email'=> $customer->email, 'customer_name'=> $customer->name, 'frequency'=>'monthly',
                                'amount'=>$amount, 'currency'=>$currency]);
                        } else {
                            \Log::info('updating the same transaction');
                            $payment->update(['status'=> $paymentStatus, 'transaction_id'=> $paymentIntentId, 'customer_email'=> $customer->email, 'customer_name'=> $customer->name, 'frequency'=>'monthly',
                                'amount'=>$amount, 'currency'=>$currency]);
                        }
                    }

                    \Log::info('payment updated successfully');

                    return response()->json('payment recorded successfully');
                }
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

    /**
     * Gives webhook event if signature is valid
     * @param $request
     * @return \Stripe\Event
     * @throws SignatureVerificationException
     */
    private function getEventIfValidSignature($request)
    {
        $payload = @file_get_contents('php://input');

        // live
//        return Webhook::constructEvent($payload, $request->header('Stripe-Signature'), $this->paymentGateway->webhook_secret);

        // test
        return Webhook::constructEvent($payload, $request->header('Stripe-Signature'), 'whsec_2kbnrTkjr5zMLKmYg4FZ3if632SjNoCg');
    }

    /**
     * Gets status by event names
     * @param string $eventName
     * @return string
     */
    private function getStatusOutOfEventName(string $eventName)
    {
        return $eventName === "invoice.payment_succeeded" ? 'COMPLETED' : 'DENIED';
    }

}
