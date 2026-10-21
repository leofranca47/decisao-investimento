<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProviderConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'provider_slug',
        'model_id',
        'api_key',
        'endpoint',
        'parameters',
        'is_active',
        'is_default',
        'notes',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'parameters' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Oculta a credencial de qualquer serialização JSON/Array.
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * Inclui a chave mascarada segura nas serializações.
     */
    protected $appends = [
        'masked_api_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retorna a chave de API mascarada para exibição segura na UI.
     */
    protected function maskedApiKey(): Attribute
    {
        return Attribute::make(
            get: function () {
                $key = $this->api_key;
                if (empty($key)) {
                    return '';
                }

                $length = strlen($key);
                if ($length <= 8) {
                    return str_repeat('•', $length);
                }

                $visibleSuffix = substr($key, -14);
                return '••••••••' . $visibleSuffix;
            }
        );
    }
}
