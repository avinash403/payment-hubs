@extends('layout.base')

@section('title')
    Payment History
@endsection

@section('content')

    {{--  Payment history table  --}}
    <div class="row">
        <table class="table">
            <thead>
            <tr>
                <th scope="col">Type</th>
                <th scope="col">Amount</th>
                <th scope="col">Initiated At</th>
                <th scope="col">Status</th>
            </tr>
            </thead>

            <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>
                        {{$payment->gateway->type->name}}
                    </td>
                    <td>
                        <div class="key-wrapper">{{$payment->amount}}</div>
                    </td>
                    <td>
                        {{$payment->created_at->format('jS F Y g:i A')}}
                    </td>
                    <td>
                        <div class="key-wrapper">{{$payment->status}}</div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

@endsection
