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

        <div class="form-group">
            <input id="is_recurring" type="checkbox" name="is_recurring">
            <label class="label">&nbsp; Make this a monthly donation</label>
        </div>

        <button id="checkout-button" class="btn btn-primary btn-block">Pay</button>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script type="text/javascript">

    let stripe = Stripe('{!! $appId !!}');
    let checkoutButton = document.getElementById('checkout-button');

    checkoutButton.addEventListener('click', function() {
        let amount = document.getElementById('amount').value;
        let is_recurring = document.getElementById('is_recurring').checked;

        if(!amount){
            return alert('Please fill an amount');
        }

        $.ajax({
            url:'/stripe/{!! $appId !!}/checkout-session',
            method: 'POST',
            data:{
                amount: amount,
                is_recurring: is_recurring ? 1 : '',
            },
            success: (response) => {
                return stripe.redirectToCheckout({ sessionId: response.id });
            },
            error: (err) => {
                return alert(JSON.stringify(err))
            }
        })
    });
</script>

@endsection
