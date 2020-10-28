<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Rest\ApiContext;
use Redirect;
use Session;

class PaypalPaymentController extends Controller
{
    /**
     * @var ApiContext
     */
    private $paypal;

    /**
     * Gives Paypal view page
     * @param $appId
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function paypal($appId)
    {
        return view('paypal', compact('appId'));
    }

    /**
     * @param $appId
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function payWithPaypal($appId, Request $request)
    {
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
    public function getPaymentStatus(Request $request)
    {
        $appId = Session::get('paypal_app_id');

        $this->setPaypalConfigObject($appId);

        $payment_id = Session::get('paypal_payment_id');

        Session::forget('paypal_payment_id');

        if (empty($request->input('PayerID')) || empty($request->input('token'))) {
            return redirect(route('payment.paypal.view'))->with('error', 'Payment failed');
        }

        $payment = Payment::get($payment_id, $this->paypal);

        $execution = new PaymentExecution();

        $execution->setPayerId($request->input('PayerID'));/**Execute the payment **/

        $result = $payment->execute($execution, $this->paypal);

        if ($result->getState() == 'approved') {
            return redirect(route('payment.paypal.view'))->with('success', 'Payment success');
        }

        return redirect(route('payment.paypal.view'))->with('error', 'Payment failed');
    }


    /**
     * Sets paypal ApiContext object with required configuration
     * @param $appId
     */
    private function setPaypalConfigObject($appId)
    {
        $appSecret = PaymentGateway::where('app_id', $appId)->value('app_secret');

        $this->paypal = new ApiContext(new OAuthTokenCredential($appId, $appSecret));

        $this->paypal->setConfig([
            'mode' => env('PAYPAL_MODE','sandbox'),
            'http.ConnectionTimeOut' => 30,
            'log.LogEnabled' => true,
            'log.FileName' => storage_path() . '/logs/paypal.log',
            'log.LogLevel' => 'ERROR'
        ]);
    }
}
