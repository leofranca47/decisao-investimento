<?php

namespace App\Filament\Pages;

use App\Services\Evolution\MonthlyEvolutionService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MonthlyComparisonPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Decisões e Aportes';

    protected static ?string $navigationLabel = 'Comparativo de Evolução';

    protected static ?string $title = 'Evolução Patrimonial & Aportes';

    protected static string $view = 'filament.pages.monthly-comparison-page';

    public function getViewData(): array
    {
        $user = Auth::user() ?? \App\Models\User::first();
        $service = app(MonthlyEvolutionService::class);
        $timeline = $service->getEvolutionTimeline($user);

        $totalContributions = collect($timeline)->sum('contributions_amount');
        $totalMarketGain = collect($timeline)->sum('market_gain_amount');
        $initialEquity = !empty($timeline) ? $timeline[0]['total_equity'] : 0.0;
        $currentEquity = !empty($timeline) ? end($timeline)['total_equity'] : 0.0;
        $totalGrowth = $currentEquity - $initialEquity;

        return [
            'timeline' => $timeline,
            'initialEquity' => $initialEquity,
            'currentEquity' => $currentEquity,
            'totalGrowth' => $totalGrowth,
            'totalContributions' => $totalContributions,
            'totalMarketGain' => $totalMarketGain,
        ];
    }
}
