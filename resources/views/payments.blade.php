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
                <th scope="col">Customer Name</th>
                <th scope="col">Customer Email</th>
                <th scope="col">Frequency</th>
                <th scope="col">Amount</th>
                <th scope="col">Initiated At</th>
                <th scope="col">Status</th>
                <th scope="col">Action</th>
            </tr>
            </thead>

            <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>
                        {{$payment->gateway->type->name}}
                    </td>
                    <td>
                        {{$payment->customer_name}}
                    </td>
                    <td>
                        {{$payment->customer_email}}
                    </td>
                    <td>
                        {{$payment->frequency ?? 'one-time'}}
                    </td>
                    <td>
                        <div class="key-wrapper">{{$payment->amount}}{{strtoupper($payment->currency)}}</div>
                    </td>
                    <td>
                        {{$payment->created_at->format('jS F Y g:i A')}}
                    </td>

                    <td>
                        {{$payment->status}}
                    </td>

                    <td>
                        @if($payment->status === 'COMPLETED')
                            {!! Form::open(['route'=>['payment.refund', $payment->id], 'method'=>'post']) !!}
                                <button type="submit" class="btn btn-primary">Refund</button>
                            {!! Form::close() !!}
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

@endsection
