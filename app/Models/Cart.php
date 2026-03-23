<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NumberFormatter;

class Cart extends Model
{
    use HasFactory;

    public const DEFAULT_SHIPPING_AMOUNT = 50.0;
    public const DEFAULT_DISCOUNT_AMOUNT = 0.0;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'visitor_id',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function shippingAmount(): float
    {
        return self::DEFAULT_SHIPPING_AMOUNT;
    }

    public function discountAmount(): float
    {
        return self::DEFAULT_DISCOUNT_AMOUNT;
    }

    public function grandTotal(float $subtotal): float
    {
        return max(0.0, $subtotal + $this->shippingAmount() - $this->discountAmount());
    }

    public function formatMoneyLabel(float $amount): string
    {
        $formatter = new NumberFormatter('fr_FR', NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($amount, 'MAD');
    }
}
