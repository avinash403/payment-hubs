<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        return view('dashboard', ['gateways'=> []]);
    }

    public function store(Request $request)
    {
        PaymentGateway::create($request->all());
    }
}
