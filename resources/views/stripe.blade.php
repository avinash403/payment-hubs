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
    <style>
        .container {
            padding: 0.5%;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col-md-12 mt-2 mb-2">
            <h3 class="text-center">Stripe Integration Experiment</h3>
            <hr>
        </div>
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
            <button type="button" class="btn btn-primary btn-block">Pay</button>
        </div>
    </div>
</div>

<script src="https://checkout.stripe.com/checkout.js"></script>

<script type="text/javascript">
    $(document).ready(function () {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    });

    $('.btn-block').click(function () {
        var amount = $('.amount').val();
        var handler = StripeCheckout.configure({
            key: '{!! $appId !!}',
            locale: 'auto',
            token: function (token) {
                // You can access the token ID with `token.id`.
                // Get the token ID to your server-side code for use.
                $('#res_token').html(JSON.stringify(token));
                $.ajax({
                    url: '{{ route("payment.stripe.process", $appId) }}',
                    method: 'post',
                    data: {
                        tokenId: token.id,
                        amount: amount
                    },
                    success: (response) => {
                        console.log(response)
                    },
                    error: (error) => {
                        console.log(error);
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
</body>
</html>
