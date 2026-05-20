<?php

/**
 * Ranqueada (Fase D) — divisões e fila. Ver roadmap/matchmaking.md
 */
return [
    'min_level' => (int) env('RANKED_MIN_LEVEL', 20),

    'pairing' => [
        /** Apenas mesma divisão antes disso (segundos) */
        'same_division_seconds' => 15,
        /** Após isso permite divisão adjacente (+1 / -1) */
        'adjacent_division_seconds' => 30,
    ],

    /*
     * Pontos por partida ranqueada (Fase 12).
     * Vitória: base + bónus por cada divisão que o oponente está acima do vencedor.
     * Derrota: base; underdog (elo abaixo do vencedor) só perde a base;
     * favorito perde base + penalidade por divisão acima do vencedor.
     */
    'scoring' => [
        'win_base' => 20,
        'win_per_tier_underdog' => 5,
        'loss_base' => -20,
        'loss_per_tier_favorite' => 5,
    ],

    /*
     * Ordem: índice 0 = divisão mais baixa (underdog vs acima).
     * min/max de pontos ranqueados (MMR) — mestre sem teto.
     */
    'divisions' => [
        ['key' => 'ferro', 'label' => 'Ferro', 'min' => 0, 'max' => 99],
        ['key' => 'bronze', 'label' => 'Bronze', 'min' => 100, 'max' => 249],
        ['key' => 'prata', 'label' => 'Prata', 'min' => 250, 'max' => 449],
        ['key' => 'ouro', 'label' => 'Ouro', 'min' => 450, 'max' => 699],
        ['key' => 'platina', 'label' => 'Platina', 'min' => 700, 'max' => 999],
        ['key' => 'diamante', 'label' => 'Diamante', 'min' => 1000, 'max' => 1399],
        ['key' => 'mestre', 'label' => 'Mestre', 'min' => 1400, 'max' => null],
    ],
];
