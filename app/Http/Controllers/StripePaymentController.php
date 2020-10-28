<?php

namespace App\Http\Controllers;

use App\Http\Requests\StripePayment;
use App\Models\PaymentGateway;
use Stripe;

class StripePaymentController extends Controller
{

    public function stripe($appId)
    {
        return view('stripe', compact('appId'));
    }

    /**
     * Sends payment request to stripe server
     * @param StripePayment $request
     * @param $appId
     * @return string
     */
    public function process(StripePayment $request, $appId)
    {
        $appSecret = PaymentGateway::where('app_id', $appId)->value('app_secret');

        Stripe::setApiKey($appSecret);

        try{
            return Stripe::charges()->create([
                'source' => $request->get('tokenId'),
                'currency' => 'USD',
                'amount' => $request->get('amount') * 100,
                'description'=> 'donation test',
                'shipping'=> [
                    "name"=> "Jenny Rosen",
                    "address"=> [
                        "line1"=> "510 Townsend St",
                        "postal_code"=> "98140",
                        "city"=> "San Francisco",
                        "state"=> "CA",
                        "country"=> "US"
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
