<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-chart-pie class="w-5 h-5 text-primary-500" />
                <span>Diagnóstico de Alocação por Classe vs Metas</span>
            </div>
        </x-slot>

        @if(!empty($diagnosis->riskAlerts))
            <div class="mb-4 space-y-2">
                @foreach($diagnosis->riskAlerts as $alert)
                    <div class="flex items-center gap-2 p-3 text-sm text-amber-800 bg-amber-50 rounded-lg dark:bg-amber-950 dark:text-amber-200 border border-amber-200 dark:border-amber-800">
                        <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-amber-600 flex-shrink-0" />
                        <span>{{ $alert }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                    <tr>
                        <th class="px-4 py-3">Classe de Ativo</th>
                        <th class="px-4 py-3 text-right">Saldo Atual (R$)</th>
                        <th class="px-4 py-3 text-right">% Atual</th>
                        <th class="px-4 py-3 text-right">Meta (%)</th>
                        <th class="px-4 py-3 text-right">Desvio (%)</th>
                        <th class="px-4 py-3 text-right">Ajuste Estimado (R$)</th>
                        <th class="px-4 py-3 text-center">Situação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse($diagnosis->classAllocations as $class)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                            <td class="px-4 py-3 font-medium">{{ $class->className }}</td>
                            <td class="px-4 py-3 text-right font-mono">R$ {{ number_format($class->currentAmount, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format($class->currentPercentage, 2, ',', '.') }}%</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ number_format($class->targetPercentage, 2, ',', '.') }}%</td>
                            <td class="px-4 py-3 text-right font-bold {{ $class->gapPercentage < 0 ? 'text-amber-600 dark:text-amber-400' : ($class->gapPercentage > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-emerald-600') }}">
                                {{ $class->gapPercentage > 0 ? '+' : '' }}{{ number_format($class->gapPercentage, 2, ',', '.') }}%
                            </td>
                            <td class="px-4 py-3 text-right font-mono {{ $class->gapAmount < 0 ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-500' }}">
                                {{ $class->gapAmount > 0 ? '+' : '' }}R$ {{ number_format($class->gapAmount, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($class->status === 'under')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">
                                        Abaixo da Meta
                                    </span>
                                @elseif($class->status === 'over')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                                        Acima da Meta
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                                        No Alvo
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">Nenhuma classe diagnosticada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
