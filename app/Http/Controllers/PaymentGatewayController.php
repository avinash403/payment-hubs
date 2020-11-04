<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePaymentGateway;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayType;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        $paymentGatewayTypes = PaymentGatewayType::pluck('name', 'id')->toArray();
        $paymentGateways = PaymentGateway::get();
        return view('dashboard', ['paymentGateways'=> $paymentGateways, 'paymentGatewayTypes'=> $paymentGatewayTypes]);
    }

    public function store(CreatePaymentGateway $request)
    {
        $paymentGatewayName = PaymentGatewayType::whereId($request->payment_gateway_type_id)->value('name');

        if($paymentGatewayName === 'Paypal' && !(new PaypalPaymentController())->isValidCredentials($request->app_id, $request->app_secret)){
            return redirect()->back()->with('error', 'Invalid Credentials');
        }

        PaymentGateway::create($request->all());

        return redirect()->back()->with('success', 'Payment Gateway added successfully');
    }

    /**
     * Gets widget code in HTML format
     * @param $appId
     * @return string
     */
    public function getWidgetCode($appId)
    {
        if($gateway = PaymentGateway::where('app_id', $appId)->first()){
            return response($gateway->widget_code);
        }
        return response('Not Found', 404);
    }
}
