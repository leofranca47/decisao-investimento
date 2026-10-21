<?php

namespace App\Filament\Resources\PortfolioImportResource\Pages;

use App\Filament\Resources\PortfolioImportResource;
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
}
