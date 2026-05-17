<?php

/**
 * Anti-boost / ranqueada (Fase D). Ajuste em produção se jogadores na mesma rede forem bloqueados indevidamente.
 */
return [
    'rank_block_same_device' => env('RANK_BLOCK_SAME_DEVICE_RANKED', true),
    'rank_block_same_ip' => env('RANK_BLOCK_SAME_IP_RANKED', true),

    /** Opcional: limite de contas novas por device em 24h (0 = desligado) */
    'max_registrations_per_device_per_day' => (int) env('MAX_REGISTRATIONS_PER_DEVICE_PER_DAY', 0),
];
