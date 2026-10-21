<?php

namespace App\Filament\Resources\PortfolioImportResource\Widgets;

use App\Models\PortfolioImport;
use App\Services\Diagnosis\PortfolioDiagnosticsService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class AllocationDiagnosisWidget extends Widget
{
    protected static string $view = 'filament.widgets.allocation-diagnosis-widget';

    protected int | string | array $columnSpan = 'full';

    public ?Model $record = null;

    protected function getViewData(): array
    {
        if (!$this->record instanceof PortfolioImport) {
            return [];
        }

        $service = app(PortfolioDiagnosticsService::class);
        $diagnosis = $service->diagnose($this->record);

        return [
            'diagnosis' => $diagnosis,
        ];
    }
}
