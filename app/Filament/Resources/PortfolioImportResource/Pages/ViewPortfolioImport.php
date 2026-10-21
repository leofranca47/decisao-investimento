<?php

namespace App\Filament\Resources\PortfolioImportResource\Pages;

use App\Filament\Resources\PortfolioImportResource;
use App\Filament\Resources\PortfolioImportResource\Widgets\AllocationDiagnosisWidget;
use App\Filament\Resources\PortfolioImportResource\Widgets\PortfolioImportStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPortfolioImport extends ViewRecord
{
    protected static string $resource = PortfolioImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PortfolioImportStatsWidget::class,
            AllocationDiagnosisWidget::class,
        ];
    }
}
