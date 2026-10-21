<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutedContribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'investment_analysis_id',
        'analysis_allocation_id',
        'asset_id',
        'amount',
        'quantity',
        'unit_price',
        'execution_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'execution_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investmentAnalysis(): BelongsTo
    {
        return $this->belongsTo(InvestmentAnalysis::class);
    }

    public function analysisAllocation(): BelongsTo
    {
        return $this->belongsTo(AnalysisAllocation::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
