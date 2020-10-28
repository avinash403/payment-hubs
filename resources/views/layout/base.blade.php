<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
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

        @yield('content')
    </div>
</body>
</html>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<style>
    .key-wrapper {
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        width: 300px;
    }
</style>
