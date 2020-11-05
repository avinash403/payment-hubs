<?php

namespace App\Models;

use Crypt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


/**
 * App\Models\Payment
 *
 * @property int $id
 * @property int $payment_gateway_id
 * @property string $payment_id
 * @property int|null $user_id
 * @property string $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment wherePaymentGatewayId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereUserId($value)
 * @mixin \Eloquent
 * @property string|null $session_id
 * @property string|null $transaction_id
 * @property string|null $customer_email
 * @property string $currency
 * @property string $status
 * @property-read \App\Models\PaymentGateway $gateway
 * @property-read mixed $encrypted_id
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCustomerEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereTransactionId($value)
 * @property string|null $customer_name
 * @property string|null $frequency
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCustomerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereFrequency($value)
 */
class Payment extends Model
{
    protected $fillable = [

        /**
         * session id which is temporarily generated during the payment to identify payment object during the course of
         * payment in different requests (payment session creation and payment status)
         */
        'session_id',

        /**
         * Identifier with which payment can be referred to in the third party service
         */
        'transaction_id',

        /**
         * Name of the customer
         */
        'customer_name',

        /**
         * Email of the customer
         */
        'customer_email',

        /**
         * frequency of the payment
         * It will either be MONTHLY or null. Can be scaled to make YEARLY
         */
        'frequency',

        /**
         * Amount which needs to be donated
         */
        'amount',

        /**
         * currency in which amount is donated
         */
        'currency',

        /**
         * reference for payment gateway
         */
        'payment_gateway_id',

        /**
         * status of the payment
         * possible values : 'PENDING', 'SUCCESS', 'FAILED'
         */
        'status',
    ];

    public function gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function getEncryptedIdAttribute()
    {
        return Crypt::encryptString($this->id);
    }
}
