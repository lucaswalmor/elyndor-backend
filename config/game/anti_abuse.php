<?php

/**
 * Anti-boost / ranqueada (Fase D). Ajuste em produção se jogadores na mesma rede forem bloqueados indevidamente.
 */
return [
    /*
     * Produção (APP_ENV=production): por defeito bloqueia mesmo IP/dispositivo na ranqueada.
     * Fora disso permite testar dois browsers na mesma rede — define env explícito se precisares.
     */
    'rank_block_same_device' => filter_var(env(
        'RANK_BLOCK_SAME_DEVICE_RANKED',
        env('APP_ENV') === 'production'
    ), FILTER_VALIDATE_BOOLEAN),

    'rank_block_same_ip' => filter_var(env(
        'RANK_BLOCK_SAME_IP_RANKED',
        env('APP_ENV') === 'production'
    ), FILTER_VALIDATE_BOOLEAN),

    /** Opcional: limite de contas novas por device em 24h (0 = desligado) */
    'max_registrations_per_device_per_day' => (int) env('MAX_REGISTRATIONS_PER_DEVICE_PER_DAY', 0),
];
