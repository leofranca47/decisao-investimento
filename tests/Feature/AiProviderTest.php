<?php

namespace Tests\Feature;

use App\Models\AiProviderConfiguration;
use App\Models\User;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Providers\GeminiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    private function createGeminiConfig(array $overrides = []): AiProviderConfiguration
    {
        return AiProviderConfiguration::create(array_merge([
            'name' => 'Google Gemini 1.5 Flash',
            'provider_slug' => 'gemini',
            'model_id' => 'gemini-1.5-flash',
            'api_key' => 'AIzaSyExampleValidKeyForTestOnly12345',
            'is_active' => true,
            'is_default' => true,
        ], $overrides));
    }

    public function test_gemini_connection_test_success(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'pong'],
                            ],
                            'role' => 'model',
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 1,
                    'candidatesTokenCount' => 1,
                    'totalTokenCount' => 2,
                ],
            ], 200),
        ]);

        $config = $this->createGeminiConfig();
        $provider = app(GeminiProvider::class);

        $result = $provider->testConnection($config);

        $this->assertTrue($result->isSuccessful);
        $this->assertStringContainsString('Conexão realizada com sucesso', $result->message);
        $this->assertGreaterThanOrEqual(0, $result->latencyMs);
    }

    public function test_gemini_connection_test_fails_with_invalid_api_key(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'API key not valid. Please pass a valid API key.',
                    'status' => 'INVALID_ARGUMENT',
                ],
            ], 400),
        ]);

        $config = $this->createGeminiConfig(['api_key' => 'SecretInvalidKeyXYZ999']);
        $provider = app(GeminiProvider::class);

        $result = $provider->testConnection($config);

        $this->assertFalse($result->isSuccessful);
        $this->assertStringContainsString('Chave de API inválida', $result->message);
        // Garante que a chave real não vaza na mensagem de erro
        $this->assertStringNotContainsString('SecretInvalidKeyXYZ999', $result->message);
    }

    public function test_gemini_connection_test_fails_with_model_not_found(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/modelo-inexistente:generateContent*' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'models/modelo-inexistente is not found for API version v1beta',
                    'status' => 'NOT_FOUND',
                ],
            ], 404),
        ]);

        $config = $this->createGeminiConfig(['model_id' => 'modelo-inexistente']);
        $provider = app(GeminiProvider::class);

        $result = $provider->testConnection($config);

        $this->assertFalse($result->isSuccessful);
        $this->assertStringContainsStringIgnoringCase('não encontrado', $result->message);
    }

    public function test_gemini_generate_content_returns_raw_response(): void
    {
        $expectedJson = json_encode(['greeting' => 'hello world']);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $expectedJson],
                            ],
                            'role' => 'model',
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 10,
                    'candidatesTokenCount' => 8,
                    'totalTokenCount' => 18,
                ],
            ], 200),
        ]);

        $config = $this->createGeminiConfig();
        $provider = app(GeminiProvider::class);

        $response = $provider->generate(
            config: $config,
            systemPrompt: 'Você é um assistente financeiro.',
            userPrompt: 'Diga olá em formato JSON.',
            options: ['json_mode' => true]
        );

        $this->assertEquals($expectedJson, $response->content);
        $this->assertEquals('gemini-1.5-flash', $response->modelUsed);
        $this->assertEquals(18, $response->tokensUsed['total'] ?? null);
    }

    public function test_ai_provider_manager_resolves_gemini_driver(): void
    {
        $manager = app(AiProviderManager::class);
        $provider = $manager->getProvider('gemini');

        $this->assertInstanceOf(GeminiProvider::class, $provider);
        $this->assertEquals('gemini', $provider->getProviderSlug());
    }
}
