<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortfolioImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reference_date',
        'file_path',
        'file_hash',
        'original_filename',
        'status',
        'total_rows',
        'accepted_rows',
        'rejected_rows',
        'validation_errors',
    ];

    protected $casts = [
        'reference_date' => 'date',
        'validation_errors' => 'array',
        'total_rows' => 'integer',
        'accepted_rows' => 'integer',
        'rejected_rows' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(PortfolioPosition::class);
    }

    public function getTotalEquityAttribute(): float
    {
        return (float) $this->positions()->sum('total_amount');
    }
}
