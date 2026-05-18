<?php

/**
 * Pesos e regras de baús — v1.0
 * Pesos por raridade usados pelo ChestAndWeeklyPoolSeeder ao gerar linhas em chest_items para o baú premium.
 */
return [
    'roulette_display' => [
        'comum' => 6,
        'rara' => 3,
        'epica' => 1,
    ],

    'spin_weights_premium' => [
        'comum' => 55,
        'rara' => 25,
        'epica' => 15,
        'lendaria' => 5,
    ],

    'spin_weights_cristal_basico' => [
        'comum' => 100,
    ],

    'weekly_offer_weights' => [
        'comum' => 69.5,
        'rara' => 20,
        'epica' => 10,
        'lendaria' => 0.5,
    ],

    'pity_epic_every' => 20,

    /** Preços de abertura de baú (Fase C) */
    'cristal_basico' => [
        'slug' => 'chest_cristal_basico',
        'cost_cristais' => 800,
    ],
    'premium_padrao' => [
        'slug' => 'chest_premium_padrao',
        'cost_moedas' => 450,
    ],

    /** Reembolso self-service só moedas; janela e TZ fixos (F / produção playtest). */
    'purchase_refund' => [
        'timezone' => env('CHEST_PURCHASE_REFUND_TZ', 'America/Sao_Paulo'),
        'window_hours' => (int) env('CHEST_PURCHASE_REFUND_WINDOW_HOURS', 24),
    ],
];
