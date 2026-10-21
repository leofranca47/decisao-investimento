<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'investment_analysis_id',
        'chunk_type',
        'chunk_text',
        'metadata',
        'embedding',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function investmentAnalysis(): BelongsTo
    {
        return $this->belongsTo(InvestmentAnalysis::class);
    }
}
