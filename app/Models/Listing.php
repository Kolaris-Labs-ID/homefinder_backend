<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Listing extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_rent' => 'boolean',
        'is_price_range' => 'boolean',
        'images' => 'array',
        'features' => 'array',
        'unit_types' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
