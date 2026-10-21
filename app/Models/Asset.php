<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticker',
        'name',
        'asset_class',
        'currency',
        'sector',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function positions(): HasMany
    {
        return $this->hasMany(PortfolioPosition::class);
    }

    public function executedContributions(): HasMany
    {
        return $this->hasMany(ExecutedContribution::class);
    }
}
