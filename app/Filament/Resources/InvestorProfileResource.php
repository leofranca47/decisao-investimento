<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvestorProfileResource\Pages;
use App\Models\InvestorProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InvestorProfileResource extends Resource
{
    protected static ?string $model = InvestorProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Planejamento';

    protected static ?string $modelLabel = 'Perfil de Investidor';

    protected static ?string $pluralModelLabel = 'Perfil de Investidor';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('user_id')
                    ->default(fn () => Auth::id() ?? 1),

                Forms\Components\Section::make('Perfil e Horizonte')
                    ->schema([
                        Forms\Components\Select::make('risk_profile')
                            ->label('Tolerância ao Risco')
                            ->options([
                                'conservative' => 'Conservador (Preservação de Capital)',
                                'moderate' => 'Moderado (Crescimento com Equilíbrio)',
                                'bold' => 'Arrojado (Foco em Renda Variável e Volatilidade Aceitável)',
                                'aggressive' => 'Agressivo (Alta Exposição a Risco e Retorno)',
                            ])
                            ->default('moderate')
                            ->required(),

                        Forms\Components\TextInput::make('horizon_years')
                            ->label('Horizonte Predominante (Anos)')
                            ->numeric()
                            ->default(10)
                            ->suffix('anos')
                            ->required(),

                        Forms\Components\Select::make('investment_focus')
                            ->label('Preferência Estratégica')
                            ->options([
                                'balanced' => 'Equilibrado (Crescimento + Renda)',
                                'growth' => 'Crescimento Patrimonial (Valorização)',
                                'dividends' => 'Geração de Renda Passiva (Dividendos / Proventos)',
                                'capital_preservation' => 'Preservação de Capital contra Inflação',
                            ])
                            ->default('balanced')
                            ->required(),

                        Forms\Components\TextInput::make('liquidity_reserve_target')
                            ->label('Meta de Reserva de Emergência (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0.00),
                    ])->columns(2),

                Forms\Components\Section::make('Limites e Restrições')
                    ->schema([
                        Forms\Components\TextInput::make('max_asset_concentration_percentage')
                            ->label('Concentração Máxima por Ativo (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(20.00),

                        Forms\Components\TextInput::make('max_sector_concentration_percentage')
                            ->label('Concentração Máxima por Setor (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(40.00),

                        Forms\Components\TagsInput::make('allowed_asset_classes')
                            ->label('Classes de Ativos Permitidas')
                            ->placeholder('Digite e pressione enter')
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('sector_restrictions')
                            ->label('Restrições de Setores ou Ativos')
                            ->placeholder('Ex: tabaco, apostas, estatais')
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('criteria')
                            ->label('Critérios Personalizados de Análise (Ex: min_dy, max_pvp)')
                            ->keyLabel('Critério')
                            ->valueLabel('Valor / Limite'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações Adicionais para a IA')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->sortable(),

                Tables\Columns\TextColumn::make('risk_profile')
                    ->label('Perfil')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'conservative' => 'Conservador',
                        'moderate' => 'Moderado',
                        'bold' => 'Arrojado',
                        'aggressive' => 'Agressivo',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'conservative' => 'info',
                        'moderate' => 'success',
                        'bold' => 'warning',
                        'aggressive' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('investment_focus')
                    ->label('Foco')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'balanced' => 'Equilibrado',
                        'growth' => 'Crescimento',
                        'dividends' => 'Renda Passiva',
                        'capital_preservation' => 'Preservação',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('horizon_years')
                    ->label('Horizonte')
                    ->suffix(' anos'),

                Tables\Columns\TextColumn::make('liquidity_reserve_target')
                    ->label('Reserva Alvo')
                    ->money('BRL'),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListInvestorProfiles::route('/'),
            'create' => Pages\CreateInvestorProfile::route('/create'),
            'edit' => Pages\EditInvestorProfile::route('/{record}/edit'),
        ];
    }
}
