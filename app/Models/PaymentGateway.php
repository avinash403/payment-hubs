<?php

namespace App\Models;

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
}
