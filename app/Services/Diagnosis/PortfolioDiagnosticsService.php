<?php

namespace App\Services\Diagnosis;

use App\Models\AllocationTarget;
use App\Models\PortfolioImport;
use App\Services\Diagnosis\Dtos\AssetConcentrationDiagnosisDto;
use App\Services\Diagnosis\Dtos\ClassAllocationDiagnosisDto;
use App\Services\Diagnosis\Dtos\PortfolioDiagnosisDto;

class PortfolioDiagnosticsService
{
    protected array $classLabels = [
        'acoes_br' => 'Ações Brasileiras',
        'fiis' => 'Fundos Imobiliários (FIIs)',
        'tesouro_direto' => 'Tesouro Direto',
        'etfs_internacionais' => 'ETFs Internacionais',
        'renda_fixa_privada' => 'Renda Fixa Privada',
        'cripto' => 'Criptoativos',
        'outros' => 'Outros',
    ];

    public function diagnose(PortfolioImport $import): PortfolioDiagnosisDto
    {
        $import->loadMissing(['positions.asset', 'user.investorProfile', 'user.allocationTargets']);

        $user = $import->user;
        $positions = $import->positions;
        $totalEquity = round((float) $positions->sum('total_amount'), 2);
        $positionsCount = $positions->count();

        $profile = $user?->investorProfile;
        $maxAssetLimit = $profile ? (float) $profile->max_asset_concentration_percentage : 20.00;

        $riskAlerts = [];

        // 1. Diagnóstico por classe de ativos
        $userTargets = $user?->allocationTargets->keyBy('asset_class') ?? collect();
        $positionsByClass = $positions->groupBy(fn ($pos) => $pos->asset->asset_class ?? 'outros');

        // Unir classes presentes nas metas e nas posições da carteira
        $allClasses = $userTargets->keys()->merge($positionsByClass->keys())->unique();

        $classAllocations = [];

        foreach ($allClasses as $classKey) {
            $classPositions = $positionsByClass->get($classKey, collect());
            $currentAmount = round((float) $classPositions->sum('total_amount'), 2);
            $currentPercentage = $totalEquity > 0 ? round(($currentAmount / $totalEquity) * 100, 2) : 0.0;

            /** @var AllocationTarget|null $targetModel */
            $targetModel = $userTargets->get($classKey);
            $targetPercentage = $targetModel ? (float) $targetModel->target_percentage : 0.0;
            $tolerance = $targetModel ? (float) $targetModel->tolerance_percentage : 2.0;

            $gapPercentage = round($currentPercentage - $targetPercentage, 2);
            $gapAmount = round(($gapPercentage / 100) * $totalEquity, 2);

            $status = 'target';
            if ($gapPercentage < -$tolerance) {
                $status = 'under';
            } elseif ($gapPercentage > $tolerance) {
                $status = 'over';
            }

            $classAllocations[$classKey] = new ClassAllocationDiagnosisDto(
                assetClass: $classKey,
                className: $this->classLabels[$classKey] ?? ucfirst(str_replace('_', ' ', $classKey)),
                currentAmount: $currentAmount,
                currentPercentage: $currentPercentage,
                targetPercentage: $targetPercentage,
                gapPercentage: $gapPercentage,
                gapAmount: $gapAmount,
                status: $status
            );
        }

        // 2. Análise de Concentração dos Ativos
        $sortedPositions = $positions->sortByDesc('total_amount')->values();
        $topAssets = [];

        foreach ($sortedPositions as $pos) {
            $assetAmount = round((float) $pos->total_amount, 2);
            $weight = $totalEquity > 0 ? round(($assetAmount / $totalEquity) * 100, 2) : 0.0;
            $isExceeding = $weight > $maxAssetLimit;

            $ticker = $pos->asset->ticker;

            if ($isExceeding) {
                $riskAlerts[] = "O ativo {$ticker} representa {$weight}% da carteira, excedendo o limite configurado de {$maxAssetLimit}%.";
            }

            $topAssets[] = new AssetConcentrationDiagnosisDto(
                ticker: $ticker,
                name: $pos->asset->name,
                assetClass: $pos->asset->asset_class,
                totalAmount: $assetAmount,
                weightPercentage: $weight,
                isExceedingLimit: $isExceeding,
                configuredLimitPercentage: $maxAssetLimit
            );
        }

        // 3. Distribuição Setorial
        $sectorDistribution = [];
        $positionsWithSector = $positions->filter(fn ($p) => !empty($p->asset->sector));

        foreach ($positionsWithSector->groupBy(fn ($p) => $p->asset->sector) as $sector => $pList) {
            $sectorAmount = (float) $pList->sum('total_amount');
            $sectorPercentage = $totalEquity > 0 ? round(($sectorAmount / $totalEquity) * 100, 2) : 0.0;
            $sectorDistribution[$sector] = $sectorPercentage;
        }

        return new PortfolioDiagnosisDto(
            totalEquity: $totalEquity,
            positionsCount: $positionsCount,
            classAllocations: $classAllocations,
            topAssets: $topAssets,
            sectorDistribution: $sectorDistribution,
            riskAlerts: $riskAlerts,
            maxAssetConcentrationLimit: $maxAssetLimit
        );
    }
}
