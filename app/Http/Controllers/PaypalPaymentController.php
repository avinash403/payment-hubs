<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Rest\ApiContext;
use Redirect;
use Session;
use Srmklive\PayPal\Services\ExpressCheckout;

class PaypalPaymentController extends Controller
{
    /**
     * @var ApiContext
     */
    private $paypal;

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

    const PRODUCT_NAME = 'Hiba-box';

    public function __construct()
    {
        $this->baseUrl = env('PAYPAL_MODE') === 'sandbox' ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        $this->client = new Client;
    }

    /**
     * Gives Paypal view page
     * @param $appId
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function create($appId, Request $request)
    {
        if($request->input('success')){
            return redirect(route('payment.paypal.view', $appId))->with('success', 'Thank you for your valuable contribution!');
        }

        $currencies = ['usd'=>'USD', 'gbp'=>'GBP'];
        return view('paypal', compact('appId', 'currencies'));
    }

    /**
     * Gives Paypal view page
     * @param $appId
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function paymentOptions($appId, Request $request)
    {
        // create a payment object
        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $planId = $request->input('is_recurring') ? $this->getPlanIdByAmount($appId, $amount, $currency) : null;

        return view('paypal-payment-option', compact('amount', 'currency', 'planId', 'appId'));
    }

    /**
     * @param $appId
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function payWithPaypal($appId, Request $request)
    {
        $this->paypal = new ExpressCheckout();

        $cart = $this->getCart(true);

        $response = $this->paypal->setExpressCheckout($cart, true);

        dd($response);
        $this->setPaypalConfigObject($appId);

        $amountNumber = $request->input('amount');

        $payer = new Payer();

        $payer->setPaymentMethod('paypal');

        $amount = new Amount();

        // todo: make currency configurable
        $amount->setCurrency('USD')
            ->setTotal($amountNumber);

        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setDescription('Your transaction description');

        $redirect_urls = new RedirectUrls();
        $redirect_urls->setReturnUrl(route('paypal.payment.status'))
            ->setCancelUrl(route('paypal.payment.status'));

        $payment = new Payment();
        $payment->setIntent('Sale')
            ->setPayer($payer)
            ->setRedirectUrls($redirect_urls)
            ->setTransactions(array($transaction));

        try {
            $payment->create($this->paypal);
        } catch (PayPalConnectionException $ex) {
            dd($ex);
            return redirect()->back()->with('error', $ex->getMessage());
        }


        foreach ($payment->getLinks() as $link) {
            if ($link->getRel() == 'approval_url') {
                $redirect_url = $link->getHref();
                break;
            }
        }

        Session::put('paypal_payment_id', $payment->getId());

        Session::put('paypal_app_id', $appId);

        if (isset($redirect_url)) {
            /** redirect to paypal **/
            return Redirect::away($redirect_url);
        }

        return redirect()->back()->with('error', 'Unknown error occurred');
    }


    /**
     * Tells paypal payment status
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function paymentStatus(Request $request)
    {
        $appId = Session::get('paypal_app_id');

        $this->setPaypalConfigObject($appId);

        $payment_id = Session::get('paypal_payment_id');

        Session::forget('paypal_payment_id');

        if (empty($request->input('PayerID')) || empty($request->input('token'))) {
            return redirect(route('payment.paypal.view', $appId))->with('error', 'Payment failed');
        }

        $payment = Payment::get($payment_id, $this->paypal);

        $execution = new PaymentExecution();

        $execution->setPayerId($request->input('PayerID'));/**Execute the payment **/

        $result = $payment->execute($execution, $this->paypal);

        if ($result->getState() == 'approved') {
            return redirect(route('payment.paypal.view', $appId))->with('success', 'Payment success');
        }

        return redirect(route('payment.paypal.view', $appId))->with('error', 'Payment failed');
    }


    private function getPlanIdByAmount($appId, $amount, $currency)
    {
        $appSecret = PaymentGateway::where('app_id', $appId)->value('app_secret');

        $accessToken = $this->getAccessToken($appId, $appSecret);

        $product = $this->getProduct($accessToken);

        $plan = $this->getPlan($accessToken, $product, $amount, $currency);

        return $plan->id;
    }

    /**
     * Sets paypal ApiContext object with required configuration
     * @param $appId
     */
    private function setPaypalConfigObject($appId)
    {
        try {
            $appSecret = PaymentGateway::where('app_id', $appId)->value('app_secret');

            $accessToken = $this->getAccessToken($appId, $appSecret);

            $product = $this->getProduct($accessToken);

            $plan = $this->getPlan($accessToken, $product, 10, 'USD');

            return $plan->id;
            dd($plan);
        }catch (ClientException $e){
                $response = $e->getResponse();
                dd(json_decode($response->getBody()->getContents()));
                $responseBodyAsString = json_decode($response->getBody()->getContents())->message;
        }

        //        $this->paypal = new ApiContext(new OAuthTokenCredential($appId, $appSecret));
//
//        $this->paypal->setConfig([
//            'mode' => env('PAYPAL_MODE','sandbox'),
//            'http.ConnectionTimeOut' => 30,
//            'log.LogEnabled' => true,
//            'log.FileName' => storage_path() . '/logs/paypal.log',
//            'log.LogLevel' => 'ERROR'
//        ]);
    }

    private function getCart($recurring)
    {

        if ($recurring) {
            return [
                // if payment is recurring cart needs only one item
                // with name, price and quantity
                'items' => [
                    [
                        'name' => 'Monthly Subscription',
                        'price' => 20,
                        'qty' => 1,
                    ],
                ],

                // return url is the url where PayPal returns after user confirmed the payment
                'return_url' => url('/paypal/express-checkout-success?recurring=1'),
                'subscription_desc' => 'Monthly Subscription',
                // every invoice id must be unique, else you'll get an error from paypal
                'invoice_id' => "",
                'invoice_description' => "",
                'cancel_url' => url('/'),
                // total is calculated by multiplying price with quantity of all cart items and then adding them up
                // in this case total is 20 because price is 20 and quantity is 1
                'total' => 20, // Total price of the cart
            ];
        }

        return [
            // if payment is not recurring cart can have many items
            // with name, price and quantity
            'items' => [
                [
                    'name' => 'Product 1',
                    'price' => 10,
                    'qty' => 1,
                ],
            ],

            // return url is the url where PayPal returns after user confirmed the payment
            'return_url' => url('/paypal/express-checkout-success'),
            // every invoice id must be unique, else you'll get an error from paypal
            'invoice_id' => config('paypal.invoice_prefix'),
            'invoice_description' => "Order #",
            'cancel_url' => url('/'),
            // total is calculated by multiplying price with quantity of all cart items and then adding them up
            // in this case total is 20 because Product 1 costs 10 (price 10 * quantity 1) and Product 2 costs 10 (price 5 * quantity 2)
            'total' => 10,
        ];
    }

    private function getAccessToken($appId, $appSecret)
    {
        $response = $this->client->post($this->baseUrl.'/v1/oauth2/token', [
            'headers'=> [
                'Accept'=> 'application/json',
                'Accept-Language'=> 'en_US'
            ],
            'form_params'=> [
                'grant_type'=>'client_credentials'
            ],
            'auth'=>[
                $appId, $appSecret
            ],
        ]);

        return json_decode($response->getBody()->getContents())->access_token;
    }

    private function getProduct($accessToken)
    {
        $response = $this->client->post($this->baseUrl.'/v1/catalogs/products', [
            'headers'=> [
                'Content-Type'=> 'application/json',
                'Authorization'=> 'Bearer '.$accessToken
            ],
            'json'=> [
                'name'=> self::PRODUCT_NAME,
                'description'=> 'Created by hiba-box'
            ]
        ]);

        return json_decode($response->getBody()->getContents());
    }

    private function getPlan($accessToken, $product, $amount, $currency)
    {
        // paypal accepts currencies only in uppercase
        $currency = strtoupper($currency);

        $response = $this->client->post($this->baseUrl.'/v1/billing/plans', [
            'headers'=> [
                'Content-Type'=> 'application/json',
                'Authorization'=> 'Bearer '.$accessToken,
                'Prefer'=> 'return=representation'
            ],
            'json'=> [
                'product_id'=> $product->id,
                'name'=> "$amount $currency per month",
                'description'=> 'Created by hiba-box',
                'billing_cycles'=> [
                    [
                        'frequency'=>[
                            'interval_unit'=>'MONTH',
                            'interval_count'=>1
                        ],
                        'tenure_type'=> 'REGULAR',
                        "sequence"=> 1,
                        'pricing_scheme'=> [
                            'fixed_price'=> [
                                'value'=> $amount,
                                'currency_code'=> $currency
                            ]
                        ]
                    ]
                ],
                'payment_preferences'=> [
                    'auto_bill_outstanding'=> true,
                    'setup_fee_failure_action'=>'CONTINUE',
                    'payment_failure_threshold'=> 3,
                ],
            ]
        ]);

        return json_decode($response->getBody()->getContents());
    }
}
