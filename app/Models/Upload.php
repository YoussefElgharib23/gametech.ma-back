<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Upload extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'size',
        'mime_type',
        'extension',
        'path',
        'uploadable_type',
        'uploadable_id',
        'position',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * Get the owning uploadable model.
     */
    public function uploadable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the public URL for the stored file.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}

