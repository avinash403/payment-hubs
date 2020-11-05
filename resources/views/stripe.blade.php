@extends('layout.widget')


@section('title')
    Stripe
@endsection

@section('content')

    <div class="form-group row" style="margin-left: 0; margin-right: 0">
        <label class="label">Donation Amount</label>
        {!! Form::number('amount', null, ['class'=>'form-control', 'id'=>'amount']) !!}
    </div>

    <div class="form-group">
        {!! Form::checkbox('is_recurring', 1, null, ['id'=> 'is_recurring', 'style'=>'transform: scale(1.2);']) !!}
        <label class="label">&nbsp; Make this a monthly donation</label>
    </div>

    <button id="checkout-button" onclick="showLoader()" type="submit" class="btn btn-success btn-block">
        Continue to donation checkout
        <span id="spinner" style="display: none" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
    </button>

    <script src="https://js.stripe.com/v3/"></script>
    <script type="text/javascript">

        let stripe = Stripe('{!! $appId !!}');
        let checkoutButton = document.getElementById('checkout-button');

        checkoutButton.addEventListener('click', function () {
            let amount = document.getElementById('amount').value;
            let is_recurring = document.getElementById('is_recurring').checked;

            if (!amount) {
                return alert('Please fill an amount');
            }

            $.ajax({
                url: '/stripe/{!! $appId !!}/checkout-session',
                method: 'POST',
                data: {
                    amount: amount,
                    is_recurring: is_recurring ? 1 : '',
                    currency: '{!! $currency !!}',
                },
                success: (response) => {
                    return stripe.redirectToCheckout({sessionId: response.id});
                },
                error: (err) => {
                    return alert(JSON.stringify(err))
                }
            })
        });
    </script>

@endsection
