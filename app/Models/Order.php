<?php

namespace App\Models;

use App\Traits\FormattedPrices;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, FormattedPrices;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uid',
        'customer_id',
        'status',
        'address',
        'city',
        'phone',
        'payment_method',
        'sub_total',
        'total',
        'shipping_price',
    ];

    /**
     * @var list<string>
     */
    protected static $formattable_columns = [
        'sub_total',
        'total',
        'shipping_price',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $model): void {
            $model->uid = (string) Str::uuid();
            if (empty($model->status)) {
                $model->status = 'new';
            }
        });
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'date:Y-m-d H:i',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
