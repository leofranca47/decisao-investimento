<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Patrimônio Inicial</div>
            <div class="text-2xl font-bold font-mono text-gray-900 dark:text-white mt-1">
                R$ {{ number_format($initialEquity, 2, ',', '.') }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Patrimônio Atual</div>
            <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1">
                R$ {{ number_format($currentEquity, 2, ',', '.') }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total de Novos Aportes</div>
            <div class="text-2xl font-bold font-mono text-primary-600 dark:text-primary-400 mt-1">
                R$ {{ number_format($totalContributions, 2, ',', '.') }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Ganho de Mercado Isolado</div>
            <div class="text-2xl font-bold font-mono {{ $totalMarketGain >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-danger-600 dark:text-danger-400' }} mt-1">
                {{ $totalMarketGain > 0 ? '+' : '' }}R$ {{ number_format($totalMarketGain, 2, ',', '.') }}
            </div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">
            Histórico Mensal: Retratos da Carteira vs Aportes Realizados
        </x-slot>
        <x-slot name="description">
            Isola o efeito dos novos aportes do rendimento/flutuação orgânica do mercado.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                    <tr>
                        <th class="px-4 py-3">Data do Retrato</th>
                        <th class="px-4 py-3 text-right">Patrimônio Total</th>
                        <th class="px-4 py-3 text-right">Aportes Efetivados</th>
                        <th class="px-4 py-3 text-right">Efeito Mercado (Orgânico)</th>
                        <th class="px-4 py-3 text-right">Variação no Período</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse($timeline as $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                            <td class="px-4 py-3 font-semibold">{{ $item['reference_date_formatted'] }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold">R$ {{ number_format($item['total_equity'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-primary-600 dark:text-primary-400">
                                {{ $item['contributions_amount'] > 0 ? 'R$ ' . number_format($item['contributions_amount'], 2, ',', '.') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ $item['market_gain_amount'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ $item['market_gain_amount'] > 0 ? '+' : '' }}R$ {{ number_format($item['market_gain_amount'], 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono {{ $item['equity_change'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-danger-600 dark:text-danger-400' }}">
                                {{ $item['equity_change'] > 0 ? '+' : '' }}R$ {{ number_format($item['equity_change'], 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                                Importe ao menos um retrato de carteira para iniciar o histórico de evolução.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
