<?php

namespace App\Models;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Visitor extends Authenticatable
{
    use HasApiTokens, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'language',
        'in_europe',
        'fingerprint',
        'utm_source',
        'utm_campaign',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'in_europe' => 'boolean',
    ];

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }
}
