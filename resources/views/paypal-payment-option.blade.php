@extends('layout.widget', ['hideDonationSuggestion' => true])


@section('title')
    Paypal
@endsection

@section('content')
    <h1 class="text-center">{!! $amount !!}{!! $currencySymbol !!}</h1>
    <br>
    @if($planId)
        <script src="https://www.paypal.com/sdk/js?client-id={!! $appId !!}&vault=true"></script>
    @else
        <script
            src="https://www.paypal.com/sdk/js?client-id={!! $appId !!}&currency={!! strtoupper($currency) !!}"></script>
    @endif
    <div id="paypal-button-container"></div>

    @if($planId)
        <script>
            paypal.Buttons({
                createSubscription: function (data, actions) {
                    return actions.subscription.create({
                        'plan_id': '{!! $planId !!}'
                    });
                },

                onApprove: function (data, actions) {
                    console.debug('onApprove', data, actions)
                    window.location.href = '{!! route('payment.paypal.view', $appId) !!}' + '?success=1&payment_id={!! $paymentId !!}&transaction_id=' + data.orderID;
                },

                onError: function (err) {
                    console.debug('onError', err)
                    window.location.href = '{!! route('payment.paypal.view', $appId) !!}' + '?error=1&payment_id={!! $paymentId !!}&transaction_id=' + data.orderID;
                },
                onCancel: function (data) {
                    console.debug('onCancel', data)
                    window.location.href = '{!! route('payment.paypal.view', $appId) !!}' + '?error=1&payment_id={!! $paymentId !!}&transaction_id=' + data.orderID;
                }
            }).render('#paypal-button-container');
        </script>

    @else
        <script>
            paypal.Buttons({

                createOrder: function (data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: '{!! $amount !!}'
                            }
                        }]
                    });
                },

                onApprove: function (data, actions) {
                    console.debug('onApprove', data, actions)
                    window.location.href = '{!! route('payment.paypal.view', $appId) !!}' + '?success=1&payment_id={!! $paymentId !!}&transaction_id=' + data.orderID;
                },

                onError: function (err) {
                    console.debug('onError', err)
                    window.location.href = '{!! route('payment.paypal.view', $appId) !!}' + '?error=1&payment_id={!! $paymentId !!}&transaction_id=' + data.orderID;
                },
                onCancel: function (data) {
                    console.debug('onCancel', data)
                    window.location.href = '{!! route('payment.paypal.view', $appId) !!}' + '?error=1&payment_id={!! $paymentId !!}&transaction_id=' + data.orderID;
                }
            }).render('#paypal-button-container');
        </script>
    @endif

@endsection
