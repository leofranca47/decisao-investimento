<?php

namespace App\Services\Diagnosis\Dtos;

class AssetConcentrationDiagnosisDto
{
    public function __construct(
        public string $ticker,
        public string $name,
        public string $assetClass,
        public float $totalAmount,
        public float $weightPercentage,
        public bool $isExceedingLimit,
        public ?float $configuredLimitPercentage = null
    ) {}
}
