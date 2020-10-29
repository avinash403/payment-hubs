@extends('layout.base')

@section('title')
    Stripe
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12 mt-2 mb-2">
            <pre id="res_token"></pre>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 offset-md-4">

            <div class="form-group">
                <label class="label">Enter Amount</label>
                <input type="text" name="amount" class="form-control amount">
            </div>
            <button id="make-payment" type="button" class="btn btn-primary btn-block">Pay</button>
        </div>
    </div>

    <script src="https://checkout.stripe.com/checkout.js"></script>

    <script type="text/javascript">

        $('#make-payment').click(function () {
            const amount = $('.amount').val();
            const handler = StripeCheckout.configure({
                key: '{!! $appId !!}',
                locale: 'auto',
                token: function (token) {
                    console.debug(JSON.stringify(token));
                    $.ajax({
                        url: '{{ route("payment.stripe.process", $appId) }}',
                        method: 'post',
                        data: {
                            tokenId: token.id,
                            amount: amount
                        },
                        success: (response) => {
                            console.debug(response)
                        },
                        error: (error) => {
                            console.debug(error);
                            alert('Oops! Something went wrong')
                        }
                    })
                }
            });

            handler.open({
                name: 'Payment Demo',
                description: 'NiceSnippets',
                amount: amount * 100
            });
        })

    </script>
@endsection
