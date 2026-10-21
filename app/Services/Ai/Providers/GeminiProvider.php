<?php

namespace App\Services\Ai\Providers;

use App\Models\AiProviderConfiguration;
use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Dtos\AiConnectionTestResult;
use App\Services\Ai\Dtos\AiRawResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider implements AiProviderInterface
{
    protected string $defaultBaseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function getProviderSlug(): string
    {
        return 'gemini';
    }

    public function testConnection(AiProviderConfiguration $config): AiConnectionTestResult
    {
        $startTime = microtime(true);
        $url = $this->buildUrl($config);
        $maxAttempts = 3;
        $attempt = 0;
        $response = null;

        try {
            while ($attempt < $maxAttempts) {
                $attempt++;
                try {
                    $response = Http::withHeaders([
                        'x-goog-api-key' => $config->api_key,
                        'Content-Type' => 'application/json',
                    ])->timeout(10)->post($url, [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => 'ping'],
                                ],
                            ],
                        ],
                    ]);

                    if ($response->successful()) {
                        break;
                    }

                    // Se for 503 ou 429, aguarda e tenta novamente
                    if (($response->status() === 503 || $response->status() === 429) && $attempt < $maxAttempts) {
                        usleep(1000000 * $attempt);
                        continue;
                    }

                    break;
                } catch (ConnectionException $e) {
                    if ($attempt >= $maxAttempts) {
                        throw $e;
                    }
                    usleep(1000000 * $attempt);
                }
            }

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response && $response->successful()) {
                return AiConnectionTestResult::success(
                    latencyMs: $latencyMs,
                    message: "Conexão realizada com sucesso com o modelo '{$config->model_id}' ({$latencyMs}ms)."
                );
            }

            $status = $response ? $response->status() : 500;
            $body = $response ? $response->json() : [];
            $rawErrorMsg = $body['error']['message'] ?? '';

            if ($status === 400 || $status === 403 || str_contains($rawErrorMsg, 'API key not valid')) {
                return AiConnectionTestResult::failure(
                    message: "Chave de API inválida ou sem permissão para acessar a API do Google Gemini.",
                    errorCode: 'INVALID_API_KEY'
                );
            }

            if ($status === 404 || str_contains($rawErrorMsg, 'not found') || str_contains($rawErrorMsg, 'no longer available')) {
                return AiConnectionTestResult::failure(
                    message: "Modelo '{$config->model_id}' não encontrado ou indisponível. Recomendado: 'gemini-3.5-flash' ou 'gemini-flash-lite-latest'.",
                    errorCode: 'MODEL_NOT_FOUND'
                );
            }

            if ($status === 429) {
                return AiConnectionTestResult::failure(
                    message: "Limite de requisições da API do Google Gemini excedido.",
                    errorCode: 'RATE_LIMIT_EXCEEDED'
                );
            }

            if ($status === 503 || str_contains($rawErrorMsg, 'high demand') || str_contains($rawErrorMsg, 'UNAVAILABLE')) {
                return AiConnectionTestResult::failure(
                    message: "O modelo '{$config->model_id}' está com alta demanda transitória no Google (Status 503). Recomendado utilizar 'gemini-3.5-flash'.",
                    errorCode: 'MODEL_HIGH_DEMAND'
                );
            }

            return AiConnectionTestResult::failure(
                message: "Falha na comunicação com o Google Gemini (Status {$status})." . ($rawErrorMsg ? " Detalhes: {$rawErrorMsg}" : ''),
                errorCode: 'HTTP_ERROR'
            );
        } catch (ConnectionException $e) {
            return AiConnectionTestResult::failure(
                message: "Tempo limite de conexão esgotado (Timeout). Verifique o acesso à internet.",
                errorCode: 'TIMEOUT'
            );
        } catch (\Throwable $e) {
            return AiConnectionTestResult::failure(
                message: "Erro inesperado ao conectar ao provedor: {$e->getMessage()}",
                errorCode: 'UNEXPECTED_ERROR'
            );
        }
    }

    public function generate(
        AiProviderConfiguration $config,
        string $systemPrompt,
        string $userPrompt,
        array $options = []
    ): AiRawResponse {
        $url = $this->buildUrl($config);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? 0.2),
            ],
        ];

        if (!empty($systemPrompt)) {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ];
        }

        if (!empty($options['json_mode'])) {
            $payload['generationConfig']['response_mime_type'] = 'application/json';
        }

        if (!empty($options['max_tokens'])) {
            $payload['generationConfig']['maxOutputTokens'] = (int) $options['max_tokens'];
        }

        $maxAttempts = 3;
        $attempt = 0;
        $response = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $response = Http::withHeaders([
                    'x-goog-api-key' => $config->api_key,
                    'Content-Type' => 'application/json',
                ])->timeout((int) ($options['timeout'] ?? 60))->post($url, $payload);

                if ($response->successful()) {
                    break;
                }

                // Se for 503 (alta demanda transitória) ou 429, aguarda com backoff exponencial e tenta de novo
                if (($response->status() === 503 || $response->status() === 429) && $attempt < $maxAttempts) {
                    usleep(1500000 * $attempt);
                    continue;
                }

                break;
            } catch (ConnectionException $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                usleep(1500000 * $attempt);
            }
        }

        if (!$response || !$response->successful()) {
            $status = $response ? $response->status() : 500;
            $body = $response ? $response->json() : [];
            $errorMsg = $body['error']['message'] ?? 'Erro desconhecido retornado pela API.';
            throw new RuntimeException("Falha na geração do Gemini (Status {$status}): {$errorMsg}");
        }

        $data = $response->json();
        $candidates = $data['candidates'] ?? [];
        if (empty($candidates)) {
            throw new RuntimeException("A API do Gemini não retornou candidatos de resposta.");
        }

        $content = $candidates[0]['content']['parts'][0]['text'] ?? '';
        $usage = $data['usageMetadata'] ?? [];

        return new AiRawResponse(
            content: $content,
            modelUsed: $config->model_id,
            tokensUsed: [
                'prompt' => $usage['promptTokenCount'] ?? null,
                'candidates' => $usage['candidatesTokenCount'] ?? null,
                'total' => $usage['totalTokenCount'] ?? null,
            ],
            rawMetadata: [
                'finishReason' => $candidates[0]['finishReason'] ?? null,
            ]
        );
    }

    protected function buildUrl(AiProviderConfiguration $config): string
    {
        $baseUrl = !empty($config->endpoint) ? rtrim($config->endpoint, '/') : $this->defaultBaseUrl;
        $model = $config->model_id;
        return "{$baseUrl}/models/{$model}:generateContent";
    }
}
