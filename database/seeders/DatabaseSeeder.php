<?php

namespace Database\Seeders;

use App\Models\AllocationTarget;
use App\Models\FinancialGoal;
use App\Models\InvestorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@invest.local'],
            [
                'name' => 'Investidor',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        InvestorProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'risk_profile' => 'moderate',
                'horizon_years' => 15,
                'investment_focus' => 'balanced',
                'allowed_asset_classes' => ['acoes_br', 'fiis', 'tesouro_direto', 'etfs_internacionais'],
                'sector_restrictions' => [],
                'liquidity_reserve_target' => 50000.00,
                'max_asset_concentration_percentage' => 15.00,
                'max_sector_concentration_percentage' => 30.00,
                'criteria' => [
                    'min_dividend_yield' => 6.0,
                    'prefer_pvp_below' => 1.05,
                ],
                'notes' => 'Foco em acumulação de patrimônio sólido com proventos reinvestidos.',
            ]
        );

        $defaultTargets = [
            ['asset_class' => 'acoes_br', 'target' => 35.00, 'min' => 25.00, 'max' => 45.00],
            ['asset_class' => 'fiis', 'target' => 30.00, 'min' => 20.00, 'max' => 40.00],
            ['asset_class' => 'tesouro_direto', 'target' => 20.00, 'min' => 10.00, 'max' => 30.00],
            ['asset_class' => 'etfs_internacionais', 'target' => 15.00, 'min' => 5.00, 'max' => 25.00],
        ];

        foreach ($defaultTargets as $target) {
            AllocationTarget::firstOrCreate(
                ['user_id' => $user->id, 'asset_class' => $target['asset_class']],
                [
                    'target_percentage' => $target['target'],
                    'min_percentage' => $target['min'],
                    'max_percentage' => $target['max'],
                    'tolerance_percentage' => 5.00,
                ]
            );
        }

        FinancialGoal::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Independência Financeira'],
            [
                'description' => 'Atingir renda passiva suficiente para cobrir os custos de vida.',
                'target_amount' => 1500000.00,
                'current_amount' => 0.00,
                'target_date' => now()->addYears(15)->format('Y-m-d'),
                'priority' => 'high',
                'liquidity_need' => 'low',
                'notes' => 'Manter foco em ativos geradores de fluxo e proteção contra inflação.',
            ]
        );
    }
}
