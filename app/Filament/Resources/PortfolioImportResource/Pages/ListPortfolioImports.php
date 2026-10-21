<?php

namespace App\Filament\Resources\PortfolioImportResource\Pages;

use App\Filament\Resources\PortfolioImportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPortfolioImports extends ListRecords
{
    protected static string $resource = PortfolioImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
