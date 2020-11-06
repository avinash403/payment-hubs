<?php


namespace App\Http\Controllers;


use App\Models\Payment;

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
}
