<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InvestmentAnalysisResource;
use App\Models\AnalysisEmbedding;
use App\Models\InvestmentAnalysis;
use App\Services\Semantic\SemanticSearchService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SemanticSearchPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Análise Mensal';

    protected static ?string $navigationLabel = 'Busca Semântica (pgvector)';

    protected static ?string $title = 'Busca Semântica no Histórico de Análises';

    protected static string $view = 'filament.pages.semantic-search-page';

    public ?string $searchQuery = '';

    public array $searchResults = [];

    public bool $hasSearched = false;

    public bool $isAvailable = false;

    public int $totalEmbeddings = 0;

    public function mount(): void
    {
        $service = app(SemanticSearchService::class);
        $this->isAvailable = $service->isAvailable();

        if ($this->isAvailable) {
            $this->totalEmbeddings = AnalysisEmbedding::count();
        }
    }

    public function search(): void
    {
        $this->validate([
            'searchQuery' => 'required|min:3',
        ], [
            'searchQuery.required' => 'Digite um termo ou pergunta para buscar.',
            'searchQuery.min' => 'Digite pelo menos 3 caracteres.',
        ]);

        $service = app(SemanticSearchService::class);

        if (!$service->isAvailable()) {
            Notification::make()
                ->danger()
                ->title('Recurso Indisponível')
                ->body('O pgvector não está ativo ou instalado nesta instância do PostgreSQL.')
                ->send();
            return;
        }

        $user = Auth::user() ?? \App\Models\User::first();

        try {
            $results = $service->searchSimilar($user, $this->searchQuery, limit: 10, maxDistance: 0.95);
            $this->searchResults = $results->map(function ($item) {
                return [
                    'analysis_id' => $item->investment_analysis_id,
                    'chunk_type' => $item->chunk_type,
                    'chunk_text' => $item->chunk_text,
                    'similarity_score' => $item->similarity_score,
                    'distance' => round($item->distance, 4),
                    'analysis_date' => $item->analysis_date,
                    'available_amount' => $item->available_amount,
                    'view_url' => InvestmentAnalysisResource::getUrl('view', ['record' => $item->investment_analysis_id]),
                ];
            })->toArray();

            $this->hasSearched = true;

            Notification::make()
                ->success()
                ->title('Busca concluída')
                ->body(count($this->searchResults) . ' trecho(s) semântico(s) encontrado(s).')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Erro na Busca Semântica')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function reindexAll(): void
    {
        $service = app(SemanticSearchService::class);

        if (!$service->isAvailable()) {
            Notification::make()
                ->danger()
                ->title('Recurso Indisponível')
                ->body('O pgvector não está ativo no banco.')
                ->send();
            return;
        }

        $user = Auth::user() ?? \App\Models\User::first();
        $analyses = InvestmentAnalysis::where('user_id', $user->id)->get();

        $totalChunks = 0;
        foreach ($analyses as $analysis) {
            try {
                $totalChunks += $service->indexAnalysis($analysis);
            } catch (\Throwable $e) {
                // continua
            }
        }

        $this->totalEmbeddings = AnalysisEmbedding::count();

        Notification::make()
            ->success()
            ->title('Reindexação concluída')
            ->body("Foram processados {$totalChunks} chunks para {$analyses->count()} análise(s).")
            ->send();
    }
}
