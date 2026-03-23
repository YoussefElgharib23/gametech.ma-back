<?php

namespace App\Models;

use App\Traits\FormattedPrices;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory, FormattedPrices;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'model_id',
        'model_type',
        'price',
        'quantity',
        'total',
    ];

    /**
     * @var list<string>
     */
    protected static $formattable_columns = [
        'price',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function model()
    {
        return $this->morphTo();
    }
}
