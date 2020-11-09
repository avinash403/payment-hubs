<form action="{!! route('payments.donate') !!}" method="post" style="border: none">
    <input type="hidden" name="app_id" value="{!! $appId !!}">
    @if($type === 'Paypal')
        <input type="image" name="submit" src="{!! url('images/donate-button-paypal.png') !!}" width="300" height="160">
    @else
        <input type="image" name="submit" src="{!! url('images/donate-button-stripe.png') !!}" width="400" height="160">
    @endif
</form>
