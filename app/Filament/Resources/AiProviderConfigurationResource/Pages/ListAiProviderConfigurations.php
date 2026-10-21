<?php

namespace App\Filament\Resources\AiProviderConfigurationResource\Pages;

use App\Filament\Resources\AiProviderConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAiProviderConfigurations extends ListRecords
{
    protected static string $resource = AiProviderConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
