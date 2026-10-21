<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'risk_profile',
        'horizon_years',
        'investment_focus',
        'allowed_asset_classes',
        'sector_restrictions',
        'liquidity_reserve_target',
        'max_asset_concentration_percentage',
        'max_sector_concentration_percentage',
        'criteria',
        'notes',
    ];

    protected $casts = [
        'horizon_years' => 'integer',
        'allowed_asset_classes' => 'array',
        'sector_restrictions' => 'array',
        'liquidity_reserve_target' => 'decimal:2',
        'max_asset_concentration_percentage' => 'decimal:2',
        'max_sector_concentration_percentage' => 'decimal:2',
        'criteria' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
