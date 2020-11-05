<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .btn-custom {
            padding: 0.5rem .86rem;
        }
    </style>
</head>
<body>
<div class="container">
    <br>
    @include('helpers.alert')
    <div class="col-md-12 mt-2 mb-2">
        <h3 class="text-center">@yield('title')</h3>
        <hr>
    </div>
    <br><br>

    <div class="row">
        <div class="col-md-4 offset-md-4">
            @if(!isset($hideDonationSuggestion))
                <div class="text-center">
                    @php
                        $suggestedAmounts = [2, 5, 10, 15, 20, 30]
                    @endphp

                    @foreach($suggestedAmounts as $suggestedAmount)
                        <button onclick="onDonationWidgetClick({{$suggestedAmount}})"
                                class="btn btn-md btn-info btn-custom">{{$currencySymbol}}{{$suggestedAmount}}</button>
                    @endforeach
                </div>
            @endif
            <br>
            @yield('content')
        </div>
    </div>

</div>
</body>
</html>
<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
@yield('footer-script')

<script>

    /**
     * Populates amount in input box
     * @param amount
     */
    function onDonationWidgetClick(amount) {
        document.getElementById('amount').value = amount;
    }

    /**
     * Changes the visibility of spinner
     */
    function showLoader() {
        if(document.getElementById('amount').value){
            document.getElementById('spinner').style.display = 'inline-block';
        }
    }
</script>
