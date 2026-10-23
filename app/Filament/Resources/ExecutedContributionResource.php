<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExecutedContributionResource\Pages;
use App\Filament\Components\MoneyInput;
use App\Models\Asset;
use App\Models\ExecutedContribution;
use App\Models\InvestmentAnalysis;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ExecutedContributionResource extends Resource
{
    protected static ?string $model = ExecutedContribution::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $navigationGroup = 'Decisões e Aportes';

    protected static ?string $modelLabel = 'Aporte Realizado';

    protected static ?string $pluralModelLabel = 'Aportes Realizados';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => Auth::id() ?? 1),

                Forms\Components\Section::make('Detalhes do Aporte Executado')
                    ->schema([
                        Forms\Components\Select::make('asset_id')
                            ->label('Ativo Comprado')
                            ->relationship('asset', 'ticker')
                            ->getOptionLabelFromRecordUsing(fn (Asset $record) => "{$record->ticker} - {$record->name}")
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\DatePicker::make('execution_date')
                            ->label('Data da Operação')
                            ->default(now())
                            ->required(),

                        MoneyInput::make('amount')
                            ->label('Valor Total Investido (R$)')
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantidade / Cotas')
                            ->numeric(),

                        MoneyInput::make('unit_price')
                            ->label('Preço Unitário Pago (R$)'),

                        Forms\Components\Select::make('investment_analysis_id')
                            ->label('Análise de Referência (Opcional)')
                            ->relationship('investmentAnalysis', 'id')
                            ->getOptionLabelFromRecordUsing(fn (InvestmentAnalysis $record) => "Análise #" . $record->id . " de " . $record->created_at->format('d/m/Y') . " (R$ " . number_format($record->available_amount, 2, ',', '.') . ")")
                            ->searchable(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações e Justificativas')
                            ->placeholder('Ex: Ordem executada a mercado. Preço ligeiramente acima do sugerido.')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('execution_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('asset.ticker')
                    ->label('Ativo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('asset.asset_class')
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

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor Investido')
                    ->money('BRL')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd')
                    ->numeric(decimalPlaces: 4)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Preço Unit.')
                    ->money('BRL')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('investment_analysis_id')
                    ->label('Origem da IA')
                    ->formatStateUsing(fn ($state) => $state ? "Análise #{$state}" : 'Aporte Avulso')
                    ->badge()
                    ->color(fn ($state) => $state ? 'primary' : 'gray'),
            ])
            ->defaultSort('execution_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('asset_class')
                    ->label('Classe')
                    ->relationship('asset', 'asset_class'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExecutedContributions::route('/'),
            'create' => Pages\CreateExecutedContribution::route('/create'),
            'edit' => Pages\EditExecutedContribution::route('/{record}/edit'),
        ];
    }
}
