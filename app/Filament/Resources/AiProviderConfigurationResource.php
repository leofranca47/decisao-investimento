<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiProviderConfigurationResource\Pages;
use App\Models\AiProviderConfiguration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AiProviderConfigurationResource extends Resource
{
    protected static ?string $model = AiProviderConfiguration::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $modelLabel = 'Provedor de IA';

    protected static ?string $pluralModelLabel = 'Provedores de IA';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação do Provedor')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Amigável')
                            ->placeholder('Ex: Google Gemini 1.5 Pro')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Select::make('provider_slug')
                            ->label('Provedor')
                            ->options([
                                'gemini' => 'Google Gemini (Oficial)',
                                'openai' => 'OpenAI',
                                'anthropic' => 'Anthropic Claude',
                            ])
                            ->default('gemini')
                            ->required(),

                        Forms\Components\TextInput::make('model_id')
                            ->label('Identificador do Modelo')
                            ->placeholder('Ex: gemini-1.5-pro ou gemini-1.5-flash')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('endpoint')
                            ->label('Endpoint Customizado (Opcional)')
                            ->placeholder('https://...')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Credenciais e Parâmetros')
                    ->schema([
                        Forms\Components\TextInput::make('api_key')
                            ->label('Chave de API (Criptografada em Repouso)')
                            ->password()
                            ->revealable()
                            ->helperText('A chave é criptografada no PostgreSQL e nunca exposta em logs ou respostas públicas.')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state)),

                        Forms\Components\KeyValue::make('parameters')
                            ->label('Parâmetros Adicionais (temperatura, max_tokens, etc.)')
                            ->keyLabel('Parâmetro')
                            ->valueLabel('Valor'),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Toggle::make('is_active')
                                ->label('Ativo para novas análises')
                                ->default(true)
                                ->required(),

                            Forms\Components\Toggle::make('is_default')
                                ->label('Definir como Provedor Padrão')
                                ->default(false)
                                ->required(),
                        ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações Internas')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('provider_slug')
                    ->label('Provedor')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'gemini' => 'info',
                        'openai' => 'success',
                        'anthropic' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('model_id')
                    ->label('Modelo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('masked_api_key')
                    ->label('Chave Mascarada'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Padrão')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Ativos'),
            ])
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
            'index' => Pages\ListAiProviderConfigurations::route('/'),
            'create' => Pages\CreateAiProviderConfiguration::route('/create'),
            'edit' => Pages\EditAiProviderConfiguration::route('/{record}/edit'),
        ];
    }
}
