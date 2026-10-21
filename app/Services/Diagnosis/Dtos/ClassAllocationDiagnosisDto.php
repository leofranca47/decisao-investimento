<?php

namespace App\Services\Diagnosis\Dtos;

class ClassAllocationDiagnosisDto
{
    public function __construct(
        public string $assetClass,
        public string $className,
        public float $currentAmount,
        public float $currentPercentage,
        public float $targetPercentage,
        public float $gapPercentage,
        public float $gapAmount,
        public string $status // 'under', 'target', 'over'
    ) {}
}
