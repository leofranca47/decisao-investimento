<?php

namespace Tests\Feature;

use App\Models\AiProviderConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAndIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_keys_are_hidden_from_json_and_array_serialization(): void
    {
        $config = AiProviderConfiguration::create([
            'name' => 'Gemini Pro',
            'provider_slug' => 'gemini',
            'model_id' => 'gemini-1.5-pro',
            'api_key' => 'SecretKeyThatMustNeverLeak123',
            'is_active' => true,
        ]);

        $serialized = $config->toArray();
        $json = $config->toJson();

        $this->assertArrayNotHasKey('api_key', $serialized);
        $this->assertStringNotContainsString('SecretKeyThatMustNeverLeak123', $json);
        $this->assertArrayHasKey('masked_api_key', $serialized);
    }

    public function test_uploaded_portfolio_files_are_stored_privately(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('carteira.csv', "TICKER;QUANTIDADE;TOTAL\nITUB4;10;300");

        $storedPath = $file->store('imports', 'local');

        // Confirma que foi gravado no disco privado local
        Storage::disk('local')->assertExists($storedPath);
        $this->assertStringStartsWith('imports/', $storedPath);

        // Garante que não está exposto no disco public
        Storage::disk('public')->assertMissing($storedPath);
    }
}
