<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentGateway;
use Crypt;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use PayPal\Api\VerifyWebhookSignature;
use Stripe\Exception\ApiErrorException;
use Stripe\Refund;

class PaypalPaymentController extends Controller
{
    /**
     * @var PaymentGateway
     */
    private $paymentGateway;

    const PRODUCT_NAME = 'Hiba-box';

    const CURRENCY = 'gbp';

    const CURRENCY_SYMBOL = '£';

    /**
     * events which webhook need to listen to
     * @internal payment.sale is for recurring payments and payment.capture is for one-time payments
     */
    const WEBHOOK_EVENTS = [
        'PAYMENT.SALE.COMPLETED', 'PAYMENT.SALE.DENIED', 'PAYMENT.SALE.PENDING',
        'PAYMENT.SALE.REFUNDED', 'PAYMENT.SALE.REVERSED',
        'PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.PENDING',
        'PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED'
    ];

    /**
     * Base url to paypal server
     * @internal will be different in sandbox environment and live environment
     * @var string
     */
    private $baseUrl = '';
    /**
     * Curl client
     * @var Client
     */
    private $client;

    public function __construct()
    {
        $this->baseUrl = env('PAYPAL_MODE') === 'sandbox' ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        $this->client = new Client;
    }

    /**
     * Gives Paypal view page
     * @param $appId
     * @param Request $request
     * @return Application|Factory|View
     */
    public function create($appId, Request $request)
    {
        if ($request->input('payment_id')) {
            $paymentId = Crypt::decryptString($request->input('payment_id'));
            $payment = Payment::find($paymentId);
            $this->setPaymentGateway($appId);
            $accessToken = $this->getAccessToken($appId, $this->paymentGateway->app_secret);

            if ($request->input('error')) {
                // need a check for non-empty session_id to avoid url manipulation hacks
                $payment->update(['status' => 'FAILED']);
                return redirect(route('payment.failed'));
            }

            if($payment->frequency === 'monthly'){
                $this->updateMonthlyPayment($accessToken, $request->input('session_id'), $paymentId);
            } else {
                $this->updateOneTimePayment($accessToken, $request->input('session_id'), $paymentId);
            }

            if ($request->input('success')) {
                return redirect(route('payment.success'));
            }
        }

        $currency = self::CURRENCY;

        $currencySymbol = self::CURRENCY_SYMBOL;

        return view('paypal', compact('appId', 'currency', 'currencySymbol'));
    }

    /**
     * Check if credentials provided is correct by requesting an access token
     * @param $appId
     * @param $appSecret
     */
    public function isValidCredentials($appId, $appSecret)
    {
        try {
            $webhook = $this->createWebhook($appId, $appSecret);

            // NOTE: paypal doesn't need any secret directly from the API but just the id of the webhook
            return ['webhook_secret'=> $webhook->id];
        } catch (Exception $e) {
            dd($e);
            return false;
        }
    }

    /**
     * Gets access token
     * @param $appId
     * @param $appSecret
     * @return mixed
     * @throws GuzzleException
     */
    private function getAccessToken($appId, $appSecret)
    {
        $response = $this->client->post($this->baseUrl . '/v1/oauth2/token', [
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US'
            ],
            'form_params' => [
                'grant_type' => 'client_credentials'
            ],
            'auth' => [
                $appId, $appSecret
            ],
        ]);

        return json_decode($response->getBody()->getContents())->access_token;
    }

    /**
     * Gives Paypal view page
     * @param $appId
     * @param Request $request
     * @return Application|Factory|View
     * @throws Exception
     */
    public function paymentOptions($appId, Request $request)
    {
        // create a payment object
        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $planId = $request->input('is_recurring') ? $this->getPlanIdByAmount($appId, $amount, $currency) : null;
        $this->setPaymentGateway($appId);

        $frequency = $request->input('is_recurring') ? 'monthly': null;

        $paymentId = $this->paymentGateway->payments()->create(['amount' => $request->input('amount'),
            'currency' => $request->input('currency'), 'status' => 'PENDING', 'frequency'=> $frequency])->encrypted_id;

        $currency = self::CURRENCY;

        $currencySymbol = self::CURRENCY_SYMBOL;

        return view('paypal-payment-option', compact('amount', 'currency', 'currencySymbol', 'planId', 'appId', 'paymentId'));
    }

    /**
     * Gets plan Id by amount (if plan does exist with that amount, it creates one)
     * @param $appId
     * @param $amount
     * @param $currency
     * @return mixed
     */
    private function getPlanIdByAmount($appId, $amount, $currency)
    {
        $appSecret = PaymentGateway::where('app_id', $appId)->value('app_secret');

        $accessToken = $this->getAccessToken($appId, $appSecret);

        if (!($product = $this->getProductIfExist(self::PRODUCT_NAME, $accessToken))) {
            $product = $this->getProduct($accessToken);
        }

        $planName = $this->getPlanName($amount, $currency);
        if (!($plan = $this->getPlanIfExist($accessToken, $planName))) {
            $plan = $this->getPlan($accessToken, $product, $amount, $currency, $planName);
        }
        return $plan->id;
    }

    /**
     * Gets if product exists
     * @param $name
     * @param $accessToken
     * @return mixed
     * @throws GuzzleException
     */
    private function getProductIfExist($name, $accessToken)
    {
        $page = 1;
        while (true) {

            $response = $this->client->get($this->baseUrl . '/v1/catalogs/products', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                'query' => [
                    'page' => $page++
                ],
            ]);

            if ($response = json_decode($response->getBody()->getContents())) {

                $products = $response->products ?? [];

                // if there are no products, just terminate
                if (!count($products)) {
                    return null;
                }

                foreach ($products as $product) {
                    if ($product->name === $name) {
                        return $product;
                    }
                }
            }
        }
    }

    /**
     * Gets product with default name. If doesn't exist, it creates one
     * @param string $accessToken
     * @return mixed
     * @throws GuzzleException
     */
    private function getProduct(string $accessToken)
    {
        $response = $this->client->post($this->baseUrl . '/v1/catalogs/products', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken
            ],
            'json' => [
                'name' => self::PRODUCT_NAME,
                'description' => 'Created by hiba-box'
            ]
        ]);

        return json_decode($response->getBody()->getContents());
    }

    /**
     * Updates user/payment details in one time payment
     * @param string $accessToken
     * @param string $orderId
     * @param string $paymentId
     * @return mixed
     * @throws GuzzleException
     */
    private function updateOneTimePayment(string $accessToken, string $orderId, string $paymentId)
    {
        $response = $this->client->get($this->baseUrl . '/v2/checkout/orders/'.$orderId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken
            ]
        ]);

        $response = json_decode($response->getBody()->getContents());

        $payment = Payment::find($paymentId);

        if(isset($response->payer)){
            $payment->customer_name = $response->payer->name->given_name. ' '.$response->payer->name->surname;
            $payment->customer_email = $response->payer->email_address;
        }


        // in case of direct payments, amount confirmation is needed. In case of subscriptions, it is already validated
        if (isset($response->purchase_units[0])){
            // order is placed as a single product with donation amount, with single transaction
            $payment->session_id = $response->purchase_units[0]->payments->captures[0]->id;;
            $payment->amount = (int)$response->purchase_units[0]->amount->value;
            $payment->currency = strtolower($response->purchase_units[0]->amount->currency_code);
        }
        $payment->save();
    }

    /**
     * Updates user/payment details in monthly payment
     * @param string $accessToken
     * @param string $subscriptionId
     * @param string $paymentId
     * @throws GuzzleException
     */
    private function updateMonthlyPayment(string $accessToken, string $subscriptionId, string $paymentId)
    {
        $response = $this->client->get($this->baseUrl . '/v1/billing/subscriptions/'.$subscriptionId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken
            ]
        ]);

        $response = json_decode($response->getBody()->getContents());

        $payment = Payment::find($paymentId);

        if(isset($response->subscriber)){
            $payment->customer_name = $response->subscriber->name->given_name. ' '.$response->subscriber->name->surname;
            $payment->customer_email = $response->subscriber->email_address;
        }

        $payment->session_id = $subscriptionId;

        // in case of direct payments, amount confirmation is needed.
        // In case of subscriptions, it is already validated using plan ID

        $payment->save();
    }

    /**
     * Gets plan if it exists by the passed name in the give product
     * @param $product
     * @param $name
     * @param $accessToken
     * @return mixed|null
     * @throws GuzzleException
     */
    private function getPlanIfExist($accessToken, $name)
    {
        $page = 1;

        while (true) {

            $response = $this->client->get($this->baseUrl . '/v1/billing/plans', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                'query' => [
                    'page' => $page++
                ],
            ]);

            if ($response = json_decode($response->getBody()->getContents())) {

                $plans = $response->plans ?? [];

                if(!count($plans)){
                   return null;
                }

                foreach ($plans as $plan) {

                    // note: comparing with amount requires an additional API call to get the details per plan,
                    // so for performance, comparing it by name
                    if ($plan->name === $name && $plan->status === 'ACTIVE') {
                        return $plan;
                    }
                }
            }
        }
    }

    /**
     * Gets plan with amount name. if doesn't exist, it creates one
     * @param $accessToken
     * @param $product
     * @param $amount
     * @param $currency
     * @return mixed
     * @throws GuzzleException
     */
    private function getPlan($accessToken, $product, $amount, $currency, $name)
    {
        // paypal accepts currencies only in uppercase
        $currency = strtoupper($currency);

        $response = $this->client->post($this->baseUrl . '/v1/billing/plans', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'Prefer' => 'return=representation'
            ],
            'json' => [
                'product_id' => $product->id,
                'name' => $name,
                'description' => 'Created by hiba-box',
                'billing_cycles' => [
                    [
                        'frequency' => [
                            'interval_unit' => 'MONTH',
                            'interval_count' => 1
                        ],
                        'tenure_type' => 'REGULAR',
                        "sequence" => 1,
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value' => $amount,
                                'currency_code' => $currency
                            ]
                        ]
                    ]
                ],
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'setup_fee_failure_action' => 'CONTINUE',
                    'payment_failure_threshold' => 3,
                ],
            ]
        ]);

        return json_decode($response->getBody()->getContents());
    }

    /**
     * Sets payment gateway as stripe and stripe's API key
     * @param $appId
     * @throws Exception
     */
    private function setPaymentGateway($appId)
    {
        // keeping $appId in a separate If to allow routes without appId too
        if ($appId) {
            if (!($this->paymentGateway = PaymentGateway::where('app_id', $appId)->first())) {
                throw new Exception('Payment gateway with give App Id is not found');
            }
        }
    }

    /**
     * Gets/makes a plan name
     * @param $amount
     * @param $currency
     * @return string
     */
    private function getPlanName($amount, $currency)
    {
        return "$amount $currency per month";
    }

    /**
     * Creates a webhook
     * @param $appId
     * @param $appSecret
     * @throws GuzzleException
     */
    private function createWebhook($appId, $appSecret)
    {
        $accessToken = $this->getAccessToken($appId,$appSecret);

        $webhookUrl = route('payment.paypal.webhook-listener', $appId);

        if(!($webhook = $this->getWebHook($webhookUrl, $accessToken))){

            $eventTypes = [];

            foreach (self::WEBHOOK_EVENTS as $webhookEvent){
                array_push($eventTypes, ['name'=>$webhookEvent]);
            }

            $response = $this->client->post($this->baseUrl . '/v1/notifications/webhooks', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $accessToken,
                    ],
                    'json' => [
                        'url'=> route('payment.paypal.webhook-listener', $appId),
                        'event_types'=> $eventTypes
                    ]
                ]
            );
            $webhook = json_decode($response->getBody()->getContents());
        }

        return $webhook;
    }

    /**
     * Gets webhook object if given url is already created as a webhook
     * @param $webhookUrl
     * @param $accessToken
     * @return mixed
     * @throws GuzzleException
     */
    private function getWebHook($webhookUrl, $accessToken)
    {
        $response = $this->client->get($this->baseUrl . '/v1/notifications/webhooks', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ]
            ]
        );

        $webhooks = json_decode($response->getBody()->getContents())->webhooks;

        foreach ($webhooks as $webhook){
            if($webhook->url === $webhookUrl){
                return $webhook;
            }
        }
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

        $accessToken = $this->getAccessToken($appId, $this->paymentGateway->app_secret);

        $this->client->post($this->baseUrl . "/v2/payments/captures/{$payment->transaction_id}/refund", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ]
            ]
        );

        $payment->status = 'REFUNDED';

        $payment->save();
    }

    /**
     * Listens for paypal events and update status and transaction ids
     * @param string $appId
     * @param Request $request
     * @throws GuzzleException
     */
    public function webhookListener(string $appId, Request $request)
    {
        \Log::info('paypal webhook received');

        \Log::info(json_encode($request->all()));

        $this->setPaymentGateway($appId);

        try{
            if($event = $this->getEventIfValidSignature($request)) {
                if (in_array($event->event_type, self::WEBHOOK_EVENTS)) {

                    /**
                     * In case of recurring payment, resource.billing_agreement_id is the session identifier
                     * and in case of one-time payment resource.id is the session identifier
                     */
                    if(isset($event->resource['billing_agreement_id'])){
                        $sessionId = $event->resource['billing_agreement_id'];
                        $transactionId = $event->resource['id'];
                    } else {
                        $sessionId = $event->resource['id'];
                        $transactionId = $event->resource['id'];
                    }

                    if($payment = Payment::where('session_id', $sessionId)->first()){
                        $payment->transaction_id = $transactionId;
                        $payment->status = $this->getStatusOutOfEventName($event->event_type);
                        $payment->save();
                    }

                    \Log::info('payment updated successfully');
                    return response()->json('payment recorded successfully');
                }
                return response()->json('event ignored');
            }
        } catch (InvalidSignatureException $e){
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
     * Verifies request signature and return the event if legit request
     * @param Request $request
     * @throws GuzzleException
     * @see https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature_post
     */
    private function getEventIfValidSignature(Request $request)
    {
        $accessToken = $this->getAccessToken($this->paymentGateway->app_id, $this->paymentGateway->app_secret);

        $json = [
            'auth_algo'=> $request->header('Paypal-Auth-Algo'),
            'cert_url'=> $request->header('Paypal-Cert-Url'),
            'transmission_id'=> $request->header('Paypal-Transmission-Id'),
            'transmission_sig'=> $request->header('Paypal-Transmission-Sig'),
            'transmission_time'=> $request->header('Paypal-Transmission-Time'),
            'webhook_id'=> $this->paymentGateway->webhook_secret,
            'webhook_event'=> json_decode(file_get_contents('php://input'))
        ];

        // GROW UP PAYPAL. THIS IS INSANE
        $response = $this->client->post($this->baseUrl . '/v1/notifications/verify-webhook-signature', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ], 'json'=> $json
            ]
        );

        if(json_decode($response->getBody()->getContents())->verification_status === 'SUCCESS'){
            return $request;
        } else {
            throw new InvalidSignatureException();
        }
    }

    /**
     * Gets status out of event name
     * @param string $eventName
     * @return string
     */
    private function getStatusOutOfEventName(string $eventName)
    {
        switch ($eventName){
            case 'PAYMENT.SALE.COMPLETED':
            case 'PAYMENT.CAPTURE.COMPLETED':
                return 'COMPLETED';

            case 'PAYMENT.SALE.DENIED':
            case 'PAYMENT.CAPTURE.DENIED':
                return 'DENIED';

            case 'PAYMENT.SALE.REFUNDED':
            case 'PAYMENT.CAPTURE.REFUNDED':
                return 'REFUNDED';

            case 'PAYMENT.SALE.REVERSED':
            case 'PAYMENT.CAPTURE.REVERSED':
                return 'REVERSED';

            default:
                return 'PENDING';
        }
    }
}
