<?php


namespace App\Http\Controllers;


use App\Models\Payment;
use Exception;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with('gateway','gateway.type:id,name')->orderBy('created_at', 'desc')->get();

        return view('payments', compact('payments'));
    }

    public function success()
    {
        $message = 'Thank you for your valuable contribution!';
        $status = "success";
        return view('status', compact('message', 'status'));
    }

    public function failed()
    {
        $message = 'Sorry, we could not process your payment. Please try again!';
        $status = "failed";
        return view('status', compact('message', 'status'));
    }


    public function refund($paymentId)
    {
        try {
            if(!($payment = Payment::find($paymentId))){
                throw new Exception('Payment Id not found');
            }

            if($payment->status !== 'COMPLETED'){
                throw new Exception('Only completed payments can be refunded');
            }

            if($payment->gateway->type->name === 'Stripe'){
                (new StripePaymentController())->refund($payment);
            }

            if($payment->gateway->type->name === 'Paypal'){
                (new PaypalPaymentController())->refund($payment);
            }

            return redirect()->back()->with('success', 'Refund initiated successfully');

        } catch (Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
