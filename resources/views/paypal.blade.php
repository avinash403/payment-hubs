@extends('layout.base')


@section('title')
    Paypal
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4 offset-md-4">

            {!! Form::open(['route'=> ['payment.paypal.options', $appId], 'method'=> 'post']) !!}
            <div class="form-group row" style="margin-left: 0; margin-right: 0">
                <label class="label">Enter Amount</label>
                <div class="col col-md-9" style="padding: 0">
                    {!! Form::number('amount', null, ['class'=>'form-control', 'id'=>'amount']) !!}
                </div>
                <div class="col col-md-3" style="padding: 0">
                    {!! Form::select('currency', $currencies, 'gbp', ['class'=>'form-control', 'id'=> 'currency']) !!}
                </div>
            </div>

            <div class="form-group">
                {!! Form::checkbox('is_recurring', 1, null, ['id'=> 'is_recurring']) !!}
                <label class="label">&nbsp; Make this a monthly donation</label>
            </div>

            <button id="checkout-button" type="submit" class="btn btn-primary btn-block">Pay</button>
            {!! Form::close() !!}

        </div>
    </div>
@endsection
