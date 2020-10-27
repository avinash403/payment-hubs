<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayType;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        $paymentGatewayTypes = PaymentGatewayType::pluck('name', 'id')->toArray();
        $paymentGateways = PaymentGateway::get();
        return view('dashboard', ['paymentGateways'=> $paymentGateways, 'paymentGatewayTypes'=> $paymentGatewayTypes]);
    }

    public function store(Request $request)
    {
        PaymentGateway::create($request->all());

        return redirect()->back()->with('success', 'Payment Gateway added successfully');
    }
}
