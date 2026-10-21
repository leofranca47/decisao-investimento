<?php

namespace App\Filament\Resources\PortfolioImportResource\Pages;

use App\Filament\Resources\PortfolioImportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPortfolioImport extends EditRecord
{
    protected static string $resource = PortfolioImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
