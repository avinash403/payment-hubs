<?php


namespace App\Http\Controllers;


use App\Models\Payment;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with('gateway','gateway.type:id,name')->get();

        return view('payments', compact('payments'));
    }
}
