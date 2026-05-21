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
        /** Meta para desbloquear o resgate do baú semanal (= barra cheia). */
        'xp_min_claim' => 1000,
        'offers_count' => 4,
        'pick_count' => 2,
        'max_legendary_in_offers' => 1,
        /**
         * Ciclo: domingo (1º login) inicia/reseta → sábado último dia para resgatar.
         * Domingo seguinte sem resgate = perde o baú. Fuso = config app.timezone.
         */
        'chest_pool_slug' => env('WEEKLY_CHEST_POOL_SLUG', 'padrao'),
    ],

    /** Legado: antes convertia excedente em cristais em `applyCardGain`. Hoje o excedente vai para `player_loot_duplicates`. */
    /** Cristais ao desfragmentar carta repetida (comum < rara < épica < lendária). */
    'duplicate_cristais' => [
        'comum' => 25,
        'rara' => 75,
        'epica' => 250,
        'lendaria' => 750,
    ],

    /** Moedas ao desfragmentar cosmético repetido, por tier de exibição do loot. */
    'duplicate_moedas' => [
        'comum' => 90,
        'rara' => 270,
        'epica' => 900,
        'lendaria' => 2700,
    ],

    /** Bônus de cristais ao subir de nível: base + per_level × novo_nível */
    'level_up_bonus_cristais' => [
        'base' => 50,
        'per_level' => 10,
    ],

    /** Loja de cartas (paga com cristais) */
    'card_shop_prices_cristais' => [
        'comum' => 50,
        'rara' => 150,
        'epica' => 500,
        'lendaria' => 1500,
    ],

    'starter_deck' => [
        'size' => 15,
        'comum' => 13,
        'rara' => 1,
        'epica' => 1,
    ],

    'decks' => [
        'max_per_user' => 30,
        'size' => 15,
        'copy_limits' => [
            'comum' => 3,
            'rara' => 5,
            'epica' => 2,
            'lendaria' => 1,
        ],
    ],
];
