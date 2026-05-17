<?php

/**
 * Regras de partida — v1.0
 * Timer: decisão de produto (20 + 5n, cap 90).
 */
return [
    /** Segundos para ambos aceitarem o pareamento antes da oferta expirar. */
    'accept_offer_seconds' => (int) env('MATCH_ACCEPT_OFFER_SECONDS', 45),

    'turn_timer' => [
        'base_seconds' => 20,
        'increment_per_turn' => 5,
        'max_seconds' => 90,
        'auto_end_turn_on_timeout' => true,
    ],

    'field' => [
        'max_units_per_player' => 5,
        'max_hand_size' => 3,
        'deck_size' => 15,
    ],

    'energy' => [
        'start' => 3,
        'max' => 10,
        'gain_per_turn' => 1,
    ],

    'ranked' => [
        'enabled' => env('RANKED_ENABLED', true),
        'min_player_level' => 20,
    ],
];
