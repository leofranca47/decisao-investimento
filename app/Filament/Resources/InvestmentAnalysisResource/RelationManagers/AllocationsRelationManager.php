<?php

namespace App\Filament\Resources\InvestmentAnalysisResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    protected static ?string $title = 'Proposta de Distribuição do Aporte';

    protected static ?string $modelLabel = 'Alocação Sugerida';

    protected static ?string $pluralModelLabel = 'Alocações Sugeridas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ticker_suggested')
                    ->label('Ticker')
                    ->disabled(),
                Forms\Components\TextInput::make('suggested_amount')
                    ->label('Valor Sugerido')
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->label('Decisão do Investidor')
                    ->options([
                        'pending' => 'Pendente de Decisão',
                        'accepted' => 'Aceito Integralmente',
                        'partial' => 'Aceito Parcialmente',
                        'rejected' => 'Rejeitado',
                        'deferred' => 'Adiado para o Próximo Mês',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('user_decision_notes')
                    ->label('Justificativa da Decisão')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ticker_suggested')
            ->columns([
                Tables\Columns\TextColumn::make('ticker_suggested')
                    ->label('Ativo')
                    ->placeholder('Diversificado')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('asset_class')
                    ->label('Classe')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'acoes_br' => 'Ações Brasileiras',
                        'fiis' => 'Fundos Imobiliários',
                        'tesouro_direto' => 'Tesouro Direto',
                        'etfs_internacionais' => 'ETFs Internacionais',
                        'renda_fixa_privada' => 'Renda Fixa',
                        'cripto' => 'Cripto',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'acoes_br' => 'info',
                        'fiis' => 'success',
                        'tesouro_direto' => 'warning',
                        'etfs_internacionais' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('suggested_amount')
                    ->label('Valor Sugerido')
                    ->money('BRL')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('share_percentage')
                    ->label('% do Aporte')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'accepted' => 'Aceito',
                        'partial' => 'Parcial',
                        'rejected' => 'Rejeitado',
                        'deferred' => 'Adiado',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'accepted' => 'success',
                        'partial' => 'warning',
                        'rejected' => 'danger',
                        'deferred' => 'gray',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('reasoning')
                    ->label('Justificativa da IA')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->reasoning),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('executeContribution')
                    ->label('Efetivar Aporte')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Valor Efetivamente Investido (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(fn (AnalysisAllocation $record) => $record->suggested_amount)
                            ->required(),
                        Forms\Components\DatePicker::make('execution_date')
                            ->label('Data da Compra / Aplicação')
                            ->default(now())
                            ->required(),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade / Cotas Compradas')
                            ->numeric(),
                        Forms\Components\TextInput::make('unit_price')
                            ->label('Preço Unitário Pago (R$)')
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observações da Execução')
                            ->placeholder('Ex: Comprado pelo home broker com preço ligeiramente melhor.'),
                    ])
                    ->action(function (AnalysisAllocation $record, array $data) {
                        $asset = $record->asset;
                        if (!$asset && !empty($record->ticker_suggested)) {
                            $asset = \App\Models\Asset::firstOrCreate(
                                ['ticker' => $record->ticker_suggested],
                                ['name' => $record->ticker_suggested, 'asset_class' => $record->asset_class]
                            );
                        }

                        if (!$asset) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Não foi possível identificar o ativo para registrar o aporte.')
                                ->send();
                            return;
                        }

                        \App\Models\ExecutedContribution::create([
                            'user_id' => $record->investmentAnalysis->user_id,
                            'investment_analysis_id' => $record->investment_analysis_id,
                            'analysis_allocation_id' => $record->id,
                            'asset_id' => $asset->id,
                            'amount' => (float) $data['amount'],
                            'quantity' => !empty($data['quantity']) ? (float) $data['quantity'] : null,
                            'unit_price' => !empty($data['unit_price']) ? (float) $data['unit_price'] : null,
                            'execution_date' => $data['execution_date'],
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $record->update(['status' => 'accepted']);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Aporte registrado com sucesso!')
                            ->body("Valor de R$ " . number_format($data['amount'], 2, ',', '.') . " registrado para {$asset->ticker}.")
                            ->send();
                    }),

                Tables\Actions\EditAction::make()->label('Decidir'),
            ])
            ->bulkActions([]);
    }
}
