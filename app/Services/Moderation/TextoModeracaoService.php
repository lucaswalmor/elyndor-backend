<?php

namespace App\Services\Moderation;

class TextoModeracaoService
{
    public function contemTermoOfensivo(string $texto): bool
    {
        $normalizado = $this->normalizar($texto);
        if ($normalizado === '') {
            return false;
        }

        foreach (config('game.community_decks.profanity_blocklist', []) as $termo) {
            $termoNormalizado = $this->normalizar((string) $termo);
            if ($termoNormalizado !== '' && str_contains($normalizado, $termoNormalizado)) {
                return true;
            }
        }

        return false;
    }

    public function validarTextoPublico(string $texto, string $rotulo): void
    {
        if ($this->contemTermoOfensivo($texto)) {
            throw new \InvalidArgumentException("{$rotulo} contém termos não permitidos.");
        }
    }

    private function normalizar(string $texto): string
    {
        $minusculo = mb_strtolower(trim($texto), 'UTF-8');

        return preg_replace('/\s+/u', ' ', $minusculo) ?? $minusculo;
    }
}
