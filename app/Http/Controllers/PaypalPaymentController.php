<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentGateway;
use Crypt;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PaypalPaymentController extends Controller
{
    private $paymentGateway;

    const PRODUCT_NAME = 'Hiba-box';

    const CURRENCY = 'gbp';

    const CURRENCY_SYMBOL = '£';

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
            $this->updatePayment($appId, $request->input('transaction_id'), $paymentId);
            $payment = Payment::find($paymentId);

            if ($request->input('success')) {
                $payment->update(['status' => 'SUCCESS', 'transaction_id' => $request->input('transaction_id')]);
                return redirect(route('payment.paypal.view', $appId))->with('success', 'Thank you for your valuable contribution!');
            }

            if ($request->input('error')) {
                $payment->update(['status' => 'FAILED', 'transaction_id' => $request->input('transaction_id')]);
                return redirect(route('payment.paypal.view', $appId))->with('error', 'Payment failed!');
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
            return (bool)$this->getAccessToken($appId, $appSecret);
        } catch (Exception $e) {
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
     * Gets product with default name. If doesn't exist, it creates one
     * @param string $appId
     * @param string $transactionId
     * @param integer $paymemtId
     * @return mixed
     * @throws GuzzleException
     */
    private function updatePayment($appId, $transactionId, $paymemtId)
    {
        $this->setPaymentGateway($appId);

        $accessToken = $this->getAccessToken($appId, $this->paymentGateway->app_secret);

        $response = $this->client->get($this->baseUrl . '/v2/checkout/orders/'.$transactionId, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken
            ]
        ]);

        $response = json_decode($response->getBody()->getContents());

        $payment = Payment::find($paymemtId);

        $payment->customer_name = $response->payer->name->given_name. ' '.$response->payer->name->surname;
        $payment->customer_email = $response->payer->email_address;
        $payment->transaction_id = $transactionId;

            // updating with the latest details to avoid client side manipulations
        // at server side while plan creation
        $payment->status = $response->status;

        // in case of direct payments, amount confirmation is needed. In case of subscriptions, it is already validated
        if (isset($response->purchase_units[0])){
            $payment->amount = (int)$response->purchase_units[0]->amount->value;
            $payment->currency = strtolower($response->purchase_units[0]->amount->currency_code);
        }
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
}
