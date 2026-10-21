<?php

namespace App\Filament\Widgets;

use App\Models\PortfolioImport;
use App\Services\Diagnosis\PortfolioDiagnosticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class LatestPortfolioOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = Auth::user();
        $latestImport = PortfolioImport::query()
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->latest('reference_date')
            ->first();

        if (!$latestImport) {
            return [
                Stat::make('Carteira', 'Nenhuma importada')
                    ->description('Importe sua carteira do Investidor10')
                    ->descriptionIcon('heroicon-m-arrow-up-tray')
                    ->color('gray'),
            ];
        }

        $service = app(PortfolioDiagnosticsService::class);
        $diagnosis = $service->diagnose($latestImport);

        $underClass = collect($diagnosis->classAllocations)
            ->filter(fn ($c) => $c->status === 'under')
            ->sortBy('gapPercentage')
            ->first();

        return [
            Stat::make('Patrimônio Atual (Investidor10)', 'R$ ' . number_format($diagnosis->totalEquity, 2, ',', '.'))
                ->description('Ref: ' . $latestImport->reference_date->format('d/m/Y') . " ({$diagnosis->positionsCount} ativos)")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Alocação que Mais Precisa de Aporte', $underClass ? "{$underClass->className}" : 'Equilibrado')
                ->description($underClass ? "Desvio de {$underClass->gapPercentage}% (Faltam R$ " . number_format(abs($underClass->gapAmount), 2, ',', '.') . ')' : 'Metas 100% no alvo')
                ->descriptionIcon($underClass ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-check-badge')
                ->color($underClass ? 'warning' : 'success'),

            Stat::make('Alertas de Concentração', count($diagnosis->riskAlerts) > 0 ? count($diagnosis->riskAlerts) . ' ativo(s) acima do limite' : 'Risco controlado')
                ->description('Teto por ativo: ' . ($diagnosis->maxAssetConcentrationLimit ?? 20) . '%')
                ->descriptionIcon(count($diagnosis->riskAlerts) > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-shield-check')
                ->color(count($diagnosis->riskAlerts) > 0 ? 'danger' : 'success'),
        ];
    }
}
