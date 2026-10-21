<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'asset_class',
        'target_percentage',
        'min_percentage',
        'max_percentage',
        'tolerance_percentage',
        'notes',
    ];

    protected $casts = [
        'target_percentage' => 'decimal:2',
        'min_percentage' => 'decimal:2',
        'max_percentage' => 'decimal:2',
        'tolerance_percentage' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
