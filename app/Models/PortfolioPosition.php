<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_import_id',
        'asset_id',
        'quantity',
        'average_price',
        'current_price',
        'total_amount',
        'weight_percentage',
        'target_percentage',
        'raw_data',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'average_price' => 'decimal:4',
        'current_price' => 'decimal:4',
        'total_amount' => 'decimal:2',
        'weight_percentage' => 'decimal:2',
        'target_percentage' => 'decimal:2',
        'raw_data' => 'array',
    ];

    public function portfolioImport(): BelongsTo
    {
        return $this->belongsTo(PortfolioImport::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
