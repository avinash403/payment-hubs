@extends('layout.base')


@section('title')
    Stripe
@endsection

@section('content')

<div class="row">
    <div class="col-md-4 offset-md-4">
        <div class="form-group row" style="margin-left: 0; margin-right: 0">
            <label class="label">Enter Amount</label>
            <div class="col col-md-9" style="padding: 0">
                {!! Form::number('amount', null, ['class'=>'form-control', 'id'=>'amount']) !!}
            </div>
            <div class="col col-md-3" style="padding: 0">
                {!! Form::select('currency', $currencies, 'gbp', ['class'=>'form-control', 'id'=> 'currency']) !!}
            </div>
        </div>

        <div class="form-group">
            {!! Form::checkbox('is_recurring', 1, null, ['id'=> 'is_recurring']) !!}
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
        let currency = document.getElementById('currency').value;

        if(!amount){
            return alert('Please fill an amount');
        }

        $.ajax({
            url:'/stripe/{!! $appId !!}/checkout-session',
            method: 'POST',
            data:{
                amount: amount,
                is_recurring: is_recurring ? 1 : '',
                currency: currency,
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
