<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe;

class StripePaymentController extends Controller
{
    public function index()
    {
        return view('stripe');
    }

    /**
     * Sends payment request to stripe server
     * @param Request $request
     * @return string
     */
    public function process(Request $request)
    {
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
