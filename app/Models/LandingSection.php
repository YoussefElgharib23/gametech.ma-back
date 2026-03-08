<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingSection extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'position',
        'config',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
        'config' => 'array',
    ];
}
