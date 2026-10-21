<?php

namespace App\Filament\Resources\InvestmentAnalysisResource\Pages;

use App\Filament\Resources\InvestmentAnalysisResource;
use App\Models\AiProviderConfiguration;
use App\Models\PortfolioImport;
use App\Services\Analysis\InvestmentAnalysisService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateInvestmentAnalysis extends CreateRecord
{
    protected static string $resource = InvestmentAnalysisResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user() ?? \App\Models\User::first();
        $import = PortfolioImport::findOrFail($data['portfolio_import_id']);
        $aiConfig = AiProviderConfiguration::findOrFail($data['ai_provider_configuration_id']);

        $service = app(InvestmentAnalysisService::class);

        try {
            $analysis = $service->runAnalysis(
                user: $user,
                import: $import,
                aiConfig: $aiConfig,
                availableAmount: (float) $data['available_amount'],
                financialData: [
                    'emergency_reserve_amount' => $data['emergency_reserve_amount'] ?? null,
                    'has_debts' => $data['has_debts'] ?? false,
                    'debts_details' => $data['debts_details'] ?? null,
                    'upcoming_expenses' => $data['upcoming_expenses'] ?? null,
                    'monthly_notes' => $data['monthly_notes'] ?? null,
                ]
            );

            Notification::make()
                ->success()
                ->title('Análise gerada com sucesso pela IA!')
                ->body("Modelo utilizado: {$aiConfig->name} ({$aiConfig->model_id}).")
                ->send();

            return $analysis;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'available_amount' => "Erro na execução da análise com IA: {$e->getMessage()}",
            ]);
        }
    }
}
