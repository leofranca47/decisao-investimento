<?php

namespace App\Services\Evolution;

use App\Models\ExecutedContribution;
use App\Models\InvestmentAnalysis;
use App\Models\PortfolioImport;
use App\Models\User;
use App\Services\Evolution\Dtos\AnalysisComplianceDto;

class MonthlyEvolutionService
{
    /**
     * Calcula o grau de cumprimento e divergência entre a sugestão da análise e o executado pelo investidor.
     */
    public function getAnalysisCompliance(InvestmentAnalysis $analysis): AnalysisComplianceDto
    {
        $totalSuggested = (float) $analysis->available_amount;
        $totalExecuted = (float) $analysis->executedContributions()->sum('amount');
        $variance = round($totalExecuted - $totalSuggested, 2);
        $compliance = $totalSuggested > 0 ? round(($totalExecuted / $totalSuggested) * 100, 2) : 0.0;

        return new AnalysisComplianceDto(
            totalSuggested: $totalSuggested,
            totalExecuted: $totalExecuted,
            varianceAmount: $variance,
            compliancePercentage: $compliance
        );
    }

    /**
     * Gera a linha do tempo de evolução patrimonial mensal diferenciando novos aportes de valorização de mercado.
     *
     * @return array<int, array{
     *     reference_date: string,
     *     total_equity: float,
     *     equity_change: float,
     *     contributions_amount: float,
     *     market_gain_amount: float
     * }>
     */
    public function getEvolutionTimeline(User $user): array
    {
        $imports = PortfolioImport::where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('reference_date', 'asc')
            ->get();

        $timeline = [];
        $previousEquity = null;
        $previousDate = null;

        foreach ($imports as $import) {
            $currentEquity = round((float) $import->positions()->sum('total_amount'), 2);
            $currentDate = $import->reference_date;

            // Busca aportes realizados no intervalo entre a importação anterior e esta
            $contributionsAmount = 0.0;
            if ($previousDate) {
                $contributionsAmount = (float) ExecutedContribution::where('user_id', $user->id)
                    ->whereBetween('execution_date', [$previousDate, $currentDate])
                    ->sum('amount');
            }

            $equityChange = $previousEquity !== null ? round($currentEquity - $previousEquity, 2) : 0.0;
            $marketGain = $previousEquity !== null ? round($equityChange - $contributionsAmount, 2) : 0.0;

            $timeline[] = [
                'id' => $import->id,
                'reference_date' => $currentDate->format('Y-m-d'),
                'reference_date_formatted' => $currentDate->format('d/m/Y'),
                'total_equity' => $currentEquity,
                'equity_change' => $equityChange,
                'contributions_amount' => $contributionsAmount,
                'market_gain_amount' => $marketGain,
            ];

            $previousEquity = $currentEquity;
            $previousDate = $currentDate;
        }

        return $timeline;
    }
}
