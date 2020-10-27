<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\PaymentGatewayType
 *
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGatewayType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGatewayType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGatewayType query()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGatewayType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGatewayType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGatewayType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentGatewayType whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PaymentGatewayType extends Model
{
    protected $fillable = ['name'];
}
