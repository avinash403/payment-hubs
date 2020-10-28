@extends('layout.base')

@section('title')
    Payment Gateway Experiment
@endsection

@section('content')
        {{--    Form to create a payment gateway    --}}
        {!! Form::open(['route'=>'payment-gateway', 'method'=>'post', 'class'=>'form-group row']) !!}
        <div class="col col-md-4">
            {!! Form::label('app_id', 'App Id') !!}
            {!! Form::text('app_id', null, ['class'=>'form-control', 'required'=> true]) !!}
        </div>

        <div class="col col-md-4">
            {!! Form::label('app_secret', 'App Secret') !!}
            {!! Form::password('app_secret', ['class'=>'form-control', 'required'=> true]) !!}
        </div>

        <div class="col col-md-2">
            {!! Form::label('payment_gateway_type_id', 'Type') !!}
            {!! Form::select('payment_gateway_type_id', $paymentGatewayTypes, null, ['class'=>'form-control']) !!}
        </div>

        <div class="col col-md-2">
            <br>
            <button class="btn btn-primary mt-2">Submit</button>
        </div>
        {!! Form::close() !!}

    <br><br>

    {{--  Payment gateway table  --}}
    <div class="row">
        <table class="table">
            <thead>
            <tr>
                <th scope="col">Type</th>
                <th scope="col">App Id</th>
                <th scope="col">App Secret</th>
                <th scope="col">Created At</th>
                <th scope="col">Actions</th>
            </tr>
            </thead>

            <tbody>
            @foreach($paymentGateways as $gateway)
                <tr>
                    <td>
                        {{$gateway->type->name}}
                    </td>
                    <td>
                        <div class="key-wrapper">{{$gateway->app_id}}</div>
                    </td>
                    <td>
                        <div class="key-wrapper">{{$gateway->app_secret}}</div>
                    </td>
                    <td>
                        {{$gateway->created_at->format('jS F Y g:i A')}}
                    </td>
                    <td>
                        <a class="btn btn-primary m-1" href="{!! $gateway->payment_url !!}">Create Payment</a>
                        <a class="btn btn-primary m-1" >Download Script</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
