<?php

namespace App\Services\Diagnosis\Dtos;

class PortfolioDiagnosisDto
{
    /**
     * @param array<string, ClassAllocationDiagnosisDto> $classAllocations
     * @param array<AssetConcentrationDiagnosisDto> $topAssets
     * @param array<string, float> $sectorDistribution
     * @param array<string> $riskAlerts
     */
    public function __construct(
        public float $totalEquity,
        public int $positionsCount,
        public array $classAllocations,
        public array $topAssets,
        public array $sectorDistribution = [],
        public array $riskAlerts = [],
        public ?float $maxAssetConcentrationLimit = null
    ) {}
}
