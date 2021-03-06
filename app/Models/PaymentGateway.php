<?php

namespace App\Models;

use App\Http\Controllers\PaypalPaymentController;
use App\Http\Controllers\StripePaymentController;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * App\Models\PaymentGateway
 *
 * @property int $id
 * @property string $app_secret
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway query()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway whereAppKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway whereAppSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property int|null $user_id
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway whereUserId($value)
 * @property int|null $payment_gateway_type_id
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway wherePaymentGatewayTypeId($value)
 * @property string $app_id
 * @property-read string $payment_url
 * @property-read \App\Models\PaymentGatewayType|null $type
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway whereAppId($value)
 * @property-read string $widget_code
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Payment[] $payments
 * @property-read int|null $payments_count
 * @property string|null $webhook_secret
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGateway whereWebhookSecret($value)
 */
class PaymentGateway extends Model
{
    protected $fillable = [
        'app_id',
        'app_secret',
        'webhook_secret',
        'payment_gateway_type_id',
    ];

    /**
     * Validation rules
     * @internal same pattern can be used if there has to have different rules for different user roles
     * @var string[]
     */
    public static $rules = [
        'app_id'=>'required|unique:payment_gateways,app_id',
        'app_secret'=>'required',
        'payment_gateway_type_id'=>'required|exists:payment_gateway_types,id',
    ];

    public function type()
    {
        return $this->belongsTo(PaymentGatewayType::class, 'payment_gateway_type_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Gives the URL at which payment can be made
     * @return string
     * @throws Exception
     * @internal currently only paypal and stripe is added to the list. In future, can scale it for more
     */
    public function getPaymentUrlAttribute()
    {
        switch ($this->type->name){
            case 'Paypal':
                return route('payment.paypal.view', $this->app_id);

            case 'Stripe':
                return route('payment.stripe.view', $this->app_id);

            default:
                throw new Exception("Payment Gateway {$this->type->name} not support");
        }
    }


    /**
     * Gives the URL at which payment can be made
     * @return string
     * @throws Exception
     * @internal currently only paypal and stripe is added to the list. In future, can scale it for more
     */
    public function getWidgetCodeAttribute()
    {
        return htmlentities(view('injectable-code', ['appId'=> $this->app_id, 'type'=> $this->type->name])
            ->render());
    }
}
