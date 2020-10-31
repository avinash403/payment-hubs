@extends('layout.base')


@section('title')
    Stripe
@endsection

@section('content')

<div class="row">
    <div class="col-md-4 offset-md-4">
        <div class="form-group">
            <label class="label">Enter Amount</label>
            <input id="amount" type="number" name="amount" class="form-control amount">
        </div>
        <button id="checkout-button" class="btn btn-primary btn-block">Pay</button>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script type="text/javascript">
    // Create an instance of the Stripe object with your publishable API key
    let stripe = Stripe('{!! $appId !!}');
    let checkoutButton = document.getElementById('checkout-button');

    checkoutButton.addEventListener('click', function() {
        let amount = document.getElementById('amount').value;
        // Create a new Checkout Session using the server-side endpoint you
        // created in step 3.
        $.ajax({
            url:'/stripe/{!! $appId !!}/checkout-session',
            method: 'POST',
            data:{
                amount: amount
            },
            success: (response) => {
                return stripe.redirectToCheckout({ sessionId: response.id });
            },
            error: () => {}
        })
    });
</script>

@endsection
