<?php

namespace App\Http\Requests;

use App\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentGateway extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return PaymentGateway::$rules;
    }
}
