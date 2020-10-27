<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StripePayment extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'tokenId'=>'required|string',
            'amount'=>'required|integer',
        ];
    }
}
