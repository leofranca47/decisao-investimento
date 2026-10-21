<?php

namespace Tests\Feature;

use App\Models\InvestorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestorProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_investor_profile_with_preferences_and_restrictions(): void
    {
        $user = User::factory()->create();

        $profile = InvestorProfile::create([
            'user_id' => $user->id,
            'risk_profile' => 'moderate',
            'horizon_years' => 10,
            'investment_focus' => 'balanced',
            'allowed_asset_classes' => ['acoes_br', 'fiis', 'tesouro_direto', 'etfs_internacionais'],
            'sector_restrictions' => ['armas', 'tabaco'],
            'liquidity_reserve_target' => 60000.00,
            'max_asset_concentration_percentage' => 15.00,
            'max_sector_concentration_percentage' => 30.00,
            'criteria' => [
                'min_dividend_yield' => 6.0,
                'prefer_pvp_below' => 1.05,
            ],
        ]);

        $this->assertDatabaseHas('investor_profiles', [
            'id' => $profile->id,
            'risk_profile' => 'moderate',
            'horizon_years' => 10,
        ]);

        $fresh = $profile->fresh();
        $this->assertIsArray($fresh->allowed_asset_classes);
        $this->assertContains('acoes_br', $fresh->allowed_asset_classes);
        $this->assertIsArray($fresh->criteria);
        $this->assertEquals(6.0, $fresh->criteria['min_dividend_yield']);
    }
}
