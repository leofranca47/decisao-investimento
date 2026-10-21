<?php

namespace Tests\Feature;

use App\Models\AiProviderConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiProviderConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_is_encrypted_at_rest_and_masked_for_display(): void
    {
        $rawKey = 'AIzaSyD1234567890SecretKeyExampleXYZ';

        $config = AiProviderConfiguration::create([
            'name' => 'Google Gemini 1.5 Pro',
            'provider_slug' => 'gemini',
            'model_id' => 'gemini-1.5-pro',
            'api_key' => $rawKey,
            'is_active' => true,
            'is_default' => true,
        ]);

        // Ao recuperar via Eloquent, deve ser descriptografado transparentemente
        $this->assertEquals($rawKey, $config->fresh()->api_key);

        // No banco de dados cru (PostgreSQL), NÃO deve conter a chave em texto puro
        $rawRow = DB::table('ai_provider_configurations')->where('id', $config->id)->first();
        $this->assertNotEquals($rawKey, $rawRow->api_key);
        $this->assertStringNotContainsString('SecretKeyExampleXYZ', $rawRow->api_key);

        // A máscara na visualização não expõe a chave completa
        $this->assertStringStartsWith('••••••••', $config->masked_api_key);
        $this->assertStringEndsWith('tKeyExampleXYZ', $config->masked_api_key);
    }
}
