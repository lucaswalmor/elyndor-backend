<?php

namespace App\Services\Cosmetics;

class CosmeticLabelService
{
    public function label(string $category, string $assetKey): string
    {
        $map = config("game.cosmetics.labels.{$category}", []);

        return $map[$assetKey] ?? $this->fallback($category, $assetKey);
    }

    private function fallback(string $category, string $key): string
    {
        $k = $key;

        if ($category === 'profile_bg') {
            if (str_starts_with($k, 'ui_bg_profile_')) {
                $k = substr($k, strlen('ui_bg_profile_'));
            } elseif (str_starts_with($k, 'ui_bg_')) {
                $k = substr($k, strlen('ui_bg_'));
            }
        } elseif ($category === 'card_back' && str_starts_with($k, 'verso_')) {
            $k = substr($k, strlen('verso_'));
        } elseif ($category === 'match_board') {
            $k = preg_replace('/^tabuleiro_/', '', $k) ?? $k;
        }

        $k = str_replace('_', ' ', $k);
        $parts = array_filter(explode(' ', $k));

        return collect($parts)
            ->map(fn (string $w) => mb_strtoupper(mb_substr($w, 0, 1)).mb_substr($w, 1))
            ->implode(' ');
    }
}
