<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'portfolio_import_id',
        'ai_provider_configuration_id',
        'provider_used',
        'model_used',
        'available_amount',
        'financial_snapshot',
        'system_diagnosis_snapshot',
        'executive_summary',
        'priorities',
        'sources',
        'limitations',
        'raw_prompt',
        'raw_response',
        'status',
        'error_message',
    ];

    protected $casts = [
        'available_amount' => 'decimal:2',
        'financial_snapshot' => 'array',
        'system_diagnosis_snapshot' => 'array',
        'priorities' => 'array',
        'sources' => 'array',
        'limitations' => 'array',
        'raw_response' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function portfolioImport(): BelongsTo
    {
        return $this->belongsTo(PortfolioImport::class);
    }

    public function aiProviderConfiguration(): BelongsTo
    {
        return $this->belongsTo(AiProviderConfiguration::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(AnalysisAllocation::class);
    }

    public function executedContributions(): HasMany
    {
        return $this->hasMany(ExecutedContribution::class);
    }
}
