@extends('layout.base')


@section('title')
    Paypal
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4 offset-md-4">

            {!! Form::open(['route'=> ['payment.paypal.process', $appId], 'method'=> 'post']) !!}
            <div class="form-group">

                <label class="label">Enter Amount</label>
                <input type="number" name="amount" class="form-control amount">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Pay</button>
            {!! Form::close() !!}

        </div>
    </div>
@endsection
