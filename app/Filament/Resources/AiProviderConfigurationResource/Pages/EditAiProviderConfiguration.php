<?php

namespace App\Filament\Resources\AiProviderConfigurationResource\Pages;

use App\Filament\Resources\AiProviderConfigurationResource;
use App\Services\Ai\AiProviderManager;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAiProviderConfiguration extends EditRecord
{
    protected static string $resource = AiProviderConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('testConnection')
                ->label('Testar Conexão')
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action(function () {
                    $manager = app(AiProviderManager::class);
                    $result = $manager->testConnection($this->record);

                    if ($result->isSuccessful) {
                        Notification::make()
                            ->success()
                            ->title('Conexão bem-sucedida!')
                            ->body($result->message)
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Falha na conexão com a IA')
                            ->body($result->message)
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
