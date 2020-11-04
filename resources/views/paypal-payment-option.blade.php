@extends('layout.base')


@section('title')
    Paypal
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4 offset-md-4">
            <h1>{!! $amount !!} &nbsp; {!! strtoupper($currency) !!}</h1>

            @if($planId)
                <script src="https://www.paypal.com/sdk/js?client-id={!! $appId !!}&vault=true"></script>
            @else
                <script src="https://www.paypal.com/sdk/js?client-id={!! $appId !!}&currency={!! strtoupper($currency) !!}"></script>
            @endif
                <div id="paypal-button-container"></div>
        </div>
    </div>

    @if($planId)
    <script>
        paypal.Buttons({

            createSubscription: function(data, actions) {
                return actions.subscription.create({
                    'plan_id': '{!! $planId !!}'
                });
            },

            onApprove: function(data, actions) {
                console.debug('onApprove',data, actions)
                window.location.href = '{!! route('payment.paypal.view', $appId) !!}'+ '?success=1';
            },

            onError: function (err) {
                console.debug('onError',err)
                alert('Something went wrong');
            },
            onCancel: function (data) {
                console.debug('onCancel',data)
                alert('Payment could not complete');
            }
        }).render('#paypal-button-container');
    </script>

    @else
        <script>
            paypal.Buttons({

                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: '{!! $amount !!}'
                            }
                        }]
                    });
                },

                onApprove: function(data, actions) {
                    console.debug('onApprove',data, actions)
                    window.location.href = '{!! route('payment.paypal.view', $appId) !!}'+ '?success=1';
                },

                onError: function (err) {
                    console.debug('onError',err)
                    alert('Something went wrong');
                },
                onCancel: function (data) {
                    console.debug('onCancel',data)
                    alert('Payment could not complete');
                }
            }).render('#paypal-button-container');
        </script>
    @endif

@endsection
