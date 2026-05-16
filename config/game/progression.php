<?php

/**
 * XP, cristais e nível — v1.0
 */
return [
    'xp_per_match' => [
        'vitoria' => 50,
        'derrota' => 20,
        'primeira_vitoria_dia_bonus' => 25,
    ],

    'cristais_per_match' => [
        'vitoria' => 25,
        'derrota' => 10,
        'primeira_vitoria_dia_bonus' => 15,
    ],

    'xp_level_formula' => [
        'base' => 350,
        'per_level' => 8,
    ],

    'weekly' => [
        'xp_cap' => 1000,
        'xp_min_claim' => 500,
        'offers_count' => 4,
        'pick_count' => 2,
        'max_legendary_in_offers' => 1,
    ],

    'starter_deck' => [
        'size' => 15,
        'comum' => 13,
        'rara' => 1,
        'epica' => 1,
    ],

    'decks' => [
        'max_per_user' => 5,
        'size' => 15,
    ],
];
