<?php

/**
 * Linhagens canônicas (slugs estáveis em BD, API e pastas de arte).
 * Rótulos com apóstrofo/acento ficam só no frontend (utils/linhagens.js).
 */
return [
    'slugs' => ['karuna', 'ybyra', 'ferroveu', 'anhanga', 'orun'],

    /** Slug antigo (facção) → slug novo (linhagem) — só para migrations. */
    'mapa_legado' => [
        'infernais' => 'karuna',
        'natureza' => 'ybyra',
        'mecanicos' => 'ferroveu',
        'mortos_vivos' => 'anhanga',
        'mortos-vivos' => 'anhanga',
        'void' => 'orun',
        'celestiais' => 'orun',
    ],

    /** Slugs de baús de cartas por linhagem (loja). */
    'baus_cartas' => [
        'karuna' => 'bau_cartas_karuna',
        'ybyra' => 'bau_cartas_ybyra',
        'ferroveu' => 'bau_cartas_ferroveu',
        'anhanga' => 'bau_cartas_anhanga',
        'orun' => 'bau_cartas_orun',
    ],
];
