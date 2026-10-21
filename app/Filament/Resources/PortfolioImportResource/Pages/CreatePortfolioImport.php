<?php

namespace App\Filament\Resources\PortfolioImportResource\Pages;

use App\Filament\Resources\PortfolioImportResource;
use App\Models\PortfolioImport;
use App\Services\Import\PortfolioImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreatePortfolioImport extends CreateRecord
{
    protected static string $resource = PortfolioImportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $filePath = Storage::disk('local')->path($data['file_upload']);
        $originalName = pathinfo($data['file_upload'], PATHINFO_BASENAME);

        $uploadedFile = new UploadedFile(
            path: $filePath,
            originalName: $originalName,
            mimeType: mime_content_type($filePath) ?: 'text/plain',
            error: null,
            test: true
        );

        $service = app(PortfolioImportService::class);

        if ($service->isDuplicateHash($uploadedFile)) {
            Notification::make()
                ->warning()
                ->title('Atenção: Arquivo idêntico já importado')
                ->body('O conteúdo deste arquivo possui o mesmo hash SHA-256 de uma importação anterior.')
                ->persistent()
                ->send();
        }

        try {
            $preview = $service->preview($uploadedFile);

            if (empty($preview->positions)) {
                throw ValidationException::withMessages([
                    'file_upload' => 'Nenhuma posição válida encontrada no arquivo. Verifique o cabeçalho e os dados.',
                ]);
            }

            $user = Auth::user() ?? \App\Models\User::first();
            $referenceDate = $data['reference_date'];

            $import = $service->confirmAndStore($user, $uploadedFile, $referenceDate, $preview);

            Notification::make()
                ->success()
                ->title('Carteira importada com sucesso!')
                ->body("{$preview->acceptedRowsCount} ativos identificados. Patrimônio total: R$ " . number_format($preview->totalEquity, 2, ',', '.'))
                ->send();

            return $import;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file_upload' => "Erro ao processar arquivo: {$e->getMessage()}",
            ]);
        }
    }
}
