<?php

namespace App\Models;

use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PaypalPaymentController;
use App\Http\Controllers\StripePaymentController;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\PaymentGateway
 *
 * @property int $id
 * @property string $app_key
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
 */
class PaymentGateway extends Model
{
    protected $fillable = [
        'app_id',
        'app_secret',
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
        switch ($this->type->name){
            case 'Stripe':
                return htmlentities((new StripePaymentController)->stripe($this->app_id)->render());

            case 'Paypal':
                return htmlentities((new PaypalPaymentController())->paypal($this->app_id)->render());

            default:
                throw new Exception("Payment Gateway {$this->type->name} not support");
        }
    }
}
