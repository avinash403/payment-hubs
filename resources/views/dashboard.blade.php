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

                        <button type="button" class="btn btn-primary m-1" data-toggle="modal" data-target="#modal-{!! $gateway->id !!}" onclick="getWidgetCode('{!! $gateway->app_id !!}', '{!! $gateway->id !!}')">Widget Code</button>

                        @component('helpers.modal', ['label'=>'Widget Code', 'id'=> 'modal-'.$gateway->id])
                            <div class="form-group">
                                <textarea class="form-control" id="widget-code-{!! $gateway->id !!}" style="height: 300px;"></textarea>
                            </div>

                            @slot('action')
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button onclick="copyToClipboard('{!! $gateway->id !!}')" id="copy-widget-code-{!! $gateway->id !!}" type="button" class="btn btn-primary">Copy To Clipboard</button>
                            @endslot
                        @endcomponent

                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

@endsection

@section('footer-script')
<script>
    {{-- ajax call to get source code of the widget --}}
    function getWidgetCode(appId, gatewayId){
        $.ajax({
            url: '{!! url('/widget-code') !!}/'+ appId,
            method: 'GET',
            success: (res) => {
                document.getElementById('widget-code-'+gatewayId).innerHTML = res;
            },
            error: (err) => {
                console.debug(err)
            }
        })
    }

    function copyToClipboard(gatewayId) {
        let copyText = document.getElementById('widget-code-'+gatewayId);
        copyText.select();
        document.execCommand("copy");
        document.getElementById('copy-widget-code-'+gatewayId).innerText = 'Copied To Clipboard';
    }

</script>
@endsection
