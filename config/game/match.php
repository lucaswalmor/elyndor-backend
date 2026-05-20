<?php

/**
 * Regras de partida — v1.0
 * Timer: decisão de produto (20 + 5n, cap 90).
 */
return [
    /** Segundos para ambos aceitarem o pareamento antes da oferta expirar. */
    'accept_offer_seconds' => (int) env('MATCH_ACCEPT_OFFER_SECONDS', 15),

    'turn_timer' => [
        'base_seconds' => 40,
        'increment_per_turn' => 0,
        'max_seconds' => 40,
        'auto_end_turn_on_timeout' => true,
    ],

    'field' => [
        'max_units_per_player' => 5,
        'max_hand_size' => 7,
        'deck_size' => 15,
    ],

    'energy' => [
        'start' => 2,
        'max' => 10,
        'gain_per_turn' => 1,
    ],

    /** Corvo / Oráculo: visão do deck inimigo (segundos); reinicia ao revelar de novo. */
    'revelacoes_duration_seconds' => 60,

    'ranked' => [
        'enabled' => env('RANKED_ENABLED', true),
        'min_player_level' => 20,
    ],
];
