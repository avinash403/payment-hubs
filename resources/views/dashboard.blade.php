<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Stripe Integration Experiment</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
</head>
<body>
<div class="container">
    <br>
        @include('helpers.alert')
    <br>
    <div class="row">
        <h1 class="col col-md-6">Payment Gateways</h1>
    </div>

    <br><br>

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
                    <td>{{$gateway->type->name}}</td>

                    <td>
                        <div class="key-wrapper">
                            {{$gateway->app_id}}
                        </div>
                    </td>
                    <td>
                        <div class="key-wrapper">
                            {{$gateway->app_secret}}
                        </div>
                    </td>
                    <td>{{$gateway->created_at->format('jS F Y g:i A')}}</td>
                    <td>

                        @php
                            $paymentUrl = $gateway->type->name === 'Paypal' ? route('payment.paypal.view', $gateway->app_id) : route('payment.stripe.view', $gateway->app_id);
                        @endphp

                        <a class="btn btn-primary m-1" href="{!! $paymentUrl !!}">Create Payment</a>

                        <a class="btn btn-primary m-1" >Download Script</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<style>
    .key-wrapper {
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        width: 300px;
    }
</style>
