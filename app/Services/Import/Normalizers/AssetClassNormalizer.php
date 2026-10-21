<?php

namespace App\Services\Import\Normalizers;

use Illuminate\Support\Str;

class AssetClassNormalizer
{
    /**
     * Normaliza descrições de tipos/classes de ativos para slugs canônicos do sistema.
     */
    public static function normalize(?string $rawClass): string
    {
        if (empty($rawClass)) {
            return 'outros';
        }

        $normalized = Str::lower(Str::ascii(trim($rawClass)));

        if (str_contains($normalized, 'acao') || str_contains($normalized, 'acoes') || str_contains($normalized, 'stock')) {
            return 'acoes_br';
        }

        if (str_contains($normalized, 'fii') || str_contains($normalized, 'imobiliario') || str_contains($normalized, 'imobiliarios')) {
            return 'fiis';
        }

        if (str_contains($normalized, 'tesouro') || $normalized === 'td') {
            return 'tesouro_direto';
        }

        if (str_contains($normalized, 'etf') || str_contains($normalized, 'bdr') || str_contains($normalized, 'internacional')) {
            return 'etfs_internacionais';
        }

        if (str_contains($normalized, 'renda fixa') || str_contains($normalized, 'cdb') || str_contains($normalized, 'lci') || str_contains($normalized, 'lca') || str_contains($normalized, 'debenture')) {
            return 'renda_fixa_privada';
        }

        if (str_contains($normalized, 'cripto') || str_contains($normalized, 'bitcoin') || str_contains($normalized, 'crypto')) {
            return 'cripto';
        }

        return 'outros';
    }
}
