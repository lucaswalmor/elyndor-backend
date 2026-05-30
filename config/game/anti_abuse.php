<?php

/**
 * Limites anti-abuse ativos no jogo.
 * Bloqueio de mesmo IP/dispositivo na ranqueada foi removido (testes / beta) — ver ideias_futuras.md.
 */
return [
    /** Opcional: limite de contas novas por device em 24h (0 = desligado) */
    'max_registrations_per_device_per_day' => (int) env('MAX_REGISTRATIONS_PER_DEVICE_PER_DAY', 0),
];
