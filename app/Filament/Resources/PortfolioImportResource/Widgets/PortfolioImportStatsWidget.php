<?php

namespace App\Filament\Resources\PortfolioImportResource\Widgets;

use App\Models\PortfolioImport;
use App\Services\Diagnosis\PortfolioDiagnosticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class PortfolioImportStatsWidget extends BaseWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (!$this->record instanceof PortfolioImport) {
            return [];
        }

        $service = app(PortfolioDiagnosticsService::class);
        $diagnosis = $service->diagnose($this->record);

        $topAsset = !empty($diagnosis->topAssets) ? $diagnosis->topAssets[0] : null;

        // Identifica maior classe abaixo da meta
        $underClass = collect($diagnosis->classAllocations)
            ->filter(fn ($c) => $c->status === 'under')
            ->sortBy('gapPercentage')
            ->first();

        return [
            Stat::make('Patrimônio Total', 'R$ ' . number_format($diagnosis->totalEquity, 2, ',', '.'))
                ->description("{$diagnosis->positionsCount} posições em carteira")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Maior Ativo', $topAsset ? "{$topAsset->ticker} ({$topAsset->weightPercentage}%)" : '-')
                ->description($topAsset ? 'R$ ' . number_format($topAsset->totalAmount, 2, ',', '.') : '')
                ->descriptionIcon($topAsset && $topAsset->isExceedingLimit ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($topAsset && $topAsset->isExceedingLimit ? 'danger' : 'primary'),

            Stat::make('Maior Desvio (Abaixo da Meta)', $underClass ? "{$underClass->className}" : 'Equilibrado')
                ->description($underClass ? "Desvio de {$underClass->gapPercentage}% (Faltam R$ " . number_format(abs($underClass->gapAmount), 2, ',', '.') . ')' : 'Metas atendidas')
                ->descriptionIcon($underClass ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-check')
                ->color($underClass ? 'warning' : 'success'),
        ];
    }
}
