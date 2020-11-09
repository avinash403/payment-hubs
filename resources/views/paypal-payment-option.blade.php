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


    <script>
        let paypalOptions = {};

        paypalOptions.onError = (err) => {
            window.location.href = '{!! route('payment.paypal.view', $appId) !!}' + '?error=1&payment_id={!! $paymentId !!}';
        }

        paypalOptions.onCancel = (data) => {
            window.location.href = '{!! route('payment.paypal.view', $appId) !!}' + '?error=1&payment_id={!! $paymentId !!}';
        }

        // if recurring payment (handled by subscription)
        @if($planId)

            paypalOptions.createSubscription = (data, actions) => actions.subscription.create({
                plan_id: '{!! $planId !!}'
            });

            paypalOptions.onApprove = (data, actions) => {
                // on subscription creation it redirects to the paypal route with subscriptionId
                window.location.href = '{!! route('payment.paypal.view', $appId) !!}' + '?success=1&payment_id={!! $paymentId !!}&session_id=' + data.subscriptionID;
            }

        @else
            paypalOptions.createOrder = (data, actions) => actions.order.create({
                purchase_units: [{ amount: { value: '{!! $amount !!}' }}]
            });

            paypalOptions.onApprove = (data, actions) => actions.order.capture().then( details => {
                // on approval it redirects to the paypal route with orderId
                window.location.href = '{!! route('payment.paypal.view', $appId) !!}' + '?success=1&payment_id={!! $paymentId !!}&session_id=' + data.orderID;
            });
        @endif

        paypal.Buttons(paypalOptions).render('#paypal-button-container');
    </script>

@endsection
