@extends('layout.widget')


@section('title')
    Paypal
@endsection

@section('content')
    {!! Form::open(['route'=> ['payment.paypal.options', $appId], 'method'=> 'post']) !!}
    <div class="form-group row" style="margin-left: 0; margin-right: 0">
        <label class="label">Donation Amount</label>
        {!! Form::number('amount', null, ['class'=>'form-control', 'id'=>'amount']) !!}
        {!! Form::hidden('currency', $currency) !!}
    </div>

    <div class="form-group">
        {!! Form::checkbox('is_recurring', 1, null, ['id'=> 'is_recurring', 'style'=>'transform: scale(1.2);']) !!}
        <label class="label">&nbsp; Make this a monthly donation</label>
    </div>

    <button id="checkout-button" type="submit" class="btn btn-success btn-block">Continue to donation checkout</button>
    {!! Form::close() !!}
@endsection
