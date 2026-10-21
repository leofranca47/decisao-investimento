<?php

namespace App\Services\Import\Helpers;

class NumberHelper
{
    /**
     * Converte representações numéricas comuns brasileiras (moeda, percentual, decimais)
     * para float com segurança.
     */
    public static function parseDecimal(mixed $value): float
    {
        if ($value === null || $value === '' || $value === '-') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = trim((string) $value);
        // Remove símbolos de moeda, percentual e espaços
        $clean = preg_replace('/[R$\s%]/u', '', $clean);

        // Se houver ponto como milhar e vírgula como decimal (ex: 1.250,50)
        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',')) {
            // Apenas vírgula decimal (ex: 1250,50 ou 15,75)
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
