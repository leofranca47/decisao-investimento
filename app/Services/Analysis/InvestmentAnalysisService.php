<?php

namespace App\Services\Analysis;

use App\Models\AiProviderConfiguration;
use App\Models\AnalysisAllocation;
use App\Models\Asset;
use App\Models\InvestmentAnalysis;
use App\Models\PortfolioImport;
use App\Models\User;
use App\Services\Ai\AiProviderManager;
use App\Services\Diagnosis\PortfolioDiagnosticsService;
use App\Services\Import\Normalizers\AssetClassNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class InvestmentAnalysisService
{
    public function __construct(
        protected PortfolioDiagnosticsService $diagnosticsService,
        protected AiProviderManager $aiManager
    ) {}

    public function runAnalysis(
        User $user,
        PortfolioImport $import,
        AiProviderConfiguration $aiConfig,
        float $availableAmount,
        array $financialData = []
    ): InvestmentAnalysis {
        // 1. Diagnóstico determinístico quantitativo
        $diagnosis = $this->diagnosticsService->diagnose($import);

        // 2. Preparar contexto estruturado
        $user->loadMissing(['financialGoals', 'investorProfile', 'allocationTargets']);
        $profile = $user->investorProfile;
        $goals = $user->financialGoals;

        $contextData = [
            'available_amount' => $availableAmount,
            'financial_data' => $financialData,
            'investor_profile' => [
                'risk_profile' => $profile?->risk_profile ?? 'moderate',
                'horizon_years' => $profile?->horizon_years ?? 10,
                'investment_focus' => $profile?->investment_focus ?? 'balanced',
                'allowed_asset_classes' => $profile?->allowed_asset_classes ?? [],
                'sector_restrictions' => $profile?->sector_restrictions ?? [],
                'max_asset_concentration_percentage' => $profile?->max_asset_concentration_percentage ?? 20.00,
                'liquidity_reserve_target' => $profile?->liquidity_reserve_target ?? 0.00,
                'criteria' => $profile?->criteria ?? [],
            ],
            'financial_goals' => $goals->map(fn ($g) => [
                'name' => $g->name,
                'target_amount' => (float) $g->target_amount,
                'target_date' => $g->target_date?->format('Y-m-d'),
                'priority' => $g->priority,
            ])->toArray(),
            'current_portfolio' => [
                'reference_date' => $import->reference_date->format('Y-m-d'),
                'total_equity' => $diagnosis->totalEquity,
                'positions_count' => $diagnosis->positionsCount,
                'class_allocations' => collect($diagnosis->classAllocations)->map(fn ($c) => [
                    'class' => $c->assetClass,
                    'name' => $c->className,
                    'current_amount' => $c->currentAmount,
                    'current_percentage' => $c->currentPercentage,
                    'target_percentage' => $c->targetPercentage,
                    'gap_percentage' => $c->gapPercentage,
                    'gap_amount' => $c->gapAmount,
                    'status' => $c->status,
                ])->toArray(),
                'top_assets' => collect($diagnosis->topAssets)->take(10)->map(fn ($a) => [
                    'ticker' => $a->ticker,
                    'name' => $a->name,
                    'class' => $a->assetClass,
                    'total_amount' => $a->totalAmount,
                    'weight_percentage' => $a->weightPercentage,
                    'is_exceeding_limit' => $a->isExceedingLimit,
                ])->toArray(),
                'risk_alerts' => $diagnosis->riskAlerts,
            ],
        ];

        // 3. Montar prompts para a IA
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = "Contexto financeiro e da carteira em JSON:\n" . json_encode($contextData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // 4. Executar geração via provedor selecionado
        $provider = $this->aiManager->getProvider($aiConfig->provider_slug);
        $rawResponse = $provider->generate(
            config: $aiConfig,
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            options: ['json_mode' => true, 'temperature' => 0.2]
        );

        // 5. Validar e decodificar a resposta estruturada
        $parsed = json_decode($rawResponse->content, true);

        if (!is_array($parsed)) {
            // Tenta extrair bloco JSON caso o modelo tenha envolvido em markdown ```json
            if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $rawResponse->content, $matches)) {
                $parsed = json_decode($matches[1], true);
            }
        }

        if (!is_array($parsed)) {
            throw new InvalidArgumentException("A resposta da inteligência artificial não retornou um JSON válido ou interpretável.");
        }

        $validator = Validator::make($parsed, [
            'executive_summary' => 'required|string',
            'allocations' => 'required|array|min:1',
            'allocations.*.asset_class' => 'required|string',
            'allocations.*.suggested_amount' => 'required|numeric',
            'allocations.*.reasoning' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException("Formato da proposta da IA inválido: " . implode(', ', $validator->errors()->all()));
        }

        // 6. Gravar análise e sugestões de forma transacional e imutável
        return DB::transaction(function () use ($user, $import, $aiConfig, $availableAmount, $financialData, $diagnosis, $systemPrompt, $userPrompt, $rawResponse, $parsed) {
            $analysis = InvestmentAnalysis::create([
                'user_id' => $user->id,
                'portfolio_import_id' => $import->id,
                'ai_provider_configuration_id' => $aiConfig->id,
                'provider_used' => $aiConfig->provider_slug,
                'model_used' => $aiConfig->model_id,
                'available_amount' => $availableAmount,
                'financial_snapshot' => $financialData,
                'system_diagnosis_snapshot' => [
                    'totalEquity' => $diagnosis->totalEquity,
                    'positionsCount' => $diagnosis->positionsCount,
                    'classAllocations' => $diagnosis->classAllocations,
                    'riskAlerts' => $diagnosis->riskAlerts,
                ],
                'executive_summary' => $parsed['executive_summary'] ?? '',
                'priorities' => $parsed['priorities'] ?? [],
                'sources' => $parsed['sources'] ?? [],
                'limitations' => $parsed['limitations'] ?? [],
                'raw_prompt' => $systemPrompt . "\n\n---\n\n" . $userPrompt,
                'raw_response' => $parsed,
                'status' => 'completed',
            ]);

            foreach ($parsed['allocations'] as $item) {
                $rawClass = $item['asset_class'] ?? 'outros';
                $normalizedClass = AssetClassNormalizer::normalize($rawClass);
                $ticker = !empty($item['ticker']) ? strtoupper(trim($item['ticker'])) : null;
                $amount = (float) $item['suggested_amount'];
                $share = !empty($item['share_percentage']) ? (float) $item['share_percentage'] : ($availableAmount > 0 ? round(($amount / $availableAmount) * 100, 2) : 0.0);

                $asset = null;
                if ($ticker) {
                    $asset = Asset::where('ticker', $ticker)->first();
                }

                AnalysisAllocation::create([
                    'investment_analysis_id' => $analysis->id,
                    'asset_id' => $asset?->id,
                    'asset_class' => $normalizedClass,
                    'ticker_suggested' => $ticker,
                    'suggested_amount' => $amount,
                    'share_percentage' => $share,
                    'reasoning' => $item['reasoning'] ?? '',
                    'risks' => $item['risks'] ?? null,
                    'discarded_alternatives' => $item['discarded_alternatives'] ?? null,
                    'status' => 'pending',
                ]);
            }

            return $analysis;
        });
    }

    protected function buildSystemPrompt(): string
    {
        return <<<PROMPT
Você é um especialista em finanças pessoais, alocação de ativos e consultoria patrimonial independente.
Sua missão é analisar o retrato da carteira de investimentos do usuário, seus objetivos, seu perfil de risco e a situação do mês, gerando uma sugestão fundamentada e estritamente estruturada de distribuição do novo aporte disponível.

REGRAS INEGOCIÁVEIS:
1. Respeite rigorosamente o valor total do aporte disponível. A soma dos valores sugeridos em `allocations` deve totalizar exatamente o valor informado (ou justifique sobra líquida em reserva se não houver ativos adequados).
2. Priorize classes e ativos que estejam abaixo da meta de alocação estipulada pelo investidor (gaps negativos), observando restrições de concentração do perfil.
3. Se a reserva de emergência informada for inferior à meta estipulada, ou houver dívidas caras, aponte nos riscos a necessidade de priorizar liquidez antes de ativos voláteis.
4. Para cada alocação, forneça justificativa fundamentada, principais riscos e alternativas que foram consideradas e descartadas.
5. Não invente tickers nem informações inexistentes.
6. A saída DEVE ser estritamente um objeto JSON com o seguinte schema:
{
  "executive_summary": "Resumo executivo claro e conciso da estratégia de alocação para este aporte.",
  "priorities": ["Prioridade 1", "Prioridade 2"],
  "allocations": [
    {
      "asset_class": "acoes_br|fiis|tesouro_direto|etfs_internacionais|renda_fixa_privada|cripto|outros",
      "ticker": "TICKER_DO_ATIVO (se aplicável)",
      "suggested_amount": 1000.00,
      "share_percentage": 50.00,
      "reasoning": "Justificativa detalhada para alocação neste ativo.",
      "risks": "Principais riscos operacionais, regulatórios ou de mercado do ativo.",
      "discarded_alternatives": "Alternativas avaliadas e motivos de descarte."
    }
  ],
  "sources": ["Fontes e bases de dados consultadas"],
  "limitations": ["Limitações conhecidas ou dados ausentes"]
}
PROMPT;
    }
}
