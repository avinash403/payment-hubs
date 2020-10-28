<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mockery\Exception;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Redirect;
use Session;

class PaypalPaymentController extends Controller
{
    private $_api_context;

    public function __construct()
    {
        $paypal_conf = \Config::get('services.paypal');

        $this->_api_context = new ApiContext(new OAuthTokenCredential(
                $paypal_conf['client_id'],
                $paypal_conf['secret'])
        );
        $this->_api_context->setConfig($paypal_conf['settings']);
    }


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
        $amountNumber = $request->input('amount');

        $payer = new Payer();

        $payer->setPaymentMethod('paypal');

//        $item_1 = new Item();

//        $item_1->setName('Item 1') /** item name **/
//        ->setCurrency('USD')
//            ->setQuantity(1)
//            ->setPrice($request->get('amount')); /** unit price **/
//        $item_list = new ItemList();
//        $item_list->setItems(array($item_1));

        $amount = new Amount();
        $amount->setCurrency('USD')
            ->setTotal($amountNumber);

        $transaction = new Transaction();
        $transaction->setAmount($amount)
//            ->setItemList($item_list)
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
            $payment->create($this->_api_context);

        } catch (Exception $ex) {
            if (\Config::get('app.debug')) {
                \Session::put('error', 'Connection timeout');
                return Redirect::route('paywithpaypal');
            } else {
                \Session::put('error', 'Some error occur, sorry for inconvenient');
                return Redirect::route('paywithpaypal');
            }
        }


        foreach ($payment->getLinks() as $link) {
            if ($link->getRel() == 'approval_url') {
                $redirect_url = $link->getHref();
                break;
            }
        }

        /** add payment ID to session **/
        Session::put('paypal_payment_id', $payment->getId());
        if (isset($redirect_url)) {
            /** redirect to paypal **/
            return Redirect::away($redirect_url);
        }
        Session::put('error', 'Unknown error occurred');
        return Redirect::route('paywithpaypal');
    }


    /**
     * Tells paypal payment status
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getPaymentStatus(Request $request)
    {
        $payment_id = Session::get('paypal_payment_id');

        Session::forget('paypal_payment_id');

        if (empty($request->input('PayerID')) || empty($request->input('token'))) {
            Session::put('error', 'Payment failed');
            return Redirect::route('/');
        }

        $payment = Payment::get($payment_id, $this->_api_context);

        $execution = new PaymentExecution();

        $execution->setPayerId($request->input('PayerID'));/**Execute the payment **/

        $result = $payment->execute($execution, $this->_api_context);

        if ($result->getState() == 'approved') {
            \Session::put('success', 'Payment success');
            return Redirect::route('/');
        }

        \Session::put('error', 'Payment failed');

        return Redirect::route('/');
    }
}
