<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'investment_analysis_id',
        'asset_id',
        'asset_class',
        'ticker_suggested',
        'suggested_amount',
        'share_percentage',
        'reasoning',
        'risks',
        'discarded_alternatives',
        'status',
        'user_decision_notes',
    ];

    protected $casts = [
        'suggested_amount' => 'decimal:2',
        'share_percentage' => 'decimal:2',
    ];

    public function investmentAnalysis(): BelongsTo
    {
        return $this->belongsTo(InvestmentAnalysis::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function executedContributions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExecutedContribution::class);
    }
}
