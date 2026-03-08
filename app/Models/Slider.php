<?php

namespace App\Models;

use App\Enums\SliderSide;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Slider extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'side',
        'link',
    ];

    /**
     * @var array<string, class-string|\UnitEnum|string>
     */
    protected $casts = [
        'side' => SliderSide::class,
    ];

    /**
     * Get the image upload associated with the slider.
     */
    public function image(): MorphOne
    {
        return $this->morphOne(Upload::class, 'uploadable');
    }
}

