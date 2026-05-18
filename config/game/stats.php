<?php

/**
 * Estatísticas públicas / presença (não expõe dados pessoais).
 */
return [
    /**
     * Considera-se "online" quem teve atividade de sessão dentro desta janela (segundos).
     * Deve ser ≥ intervalo de touch do UserSessionTracker (~50s).
     */
    'online_presence_seconds' => (int) env('ONLINE_PRESENCE_WINDOW_SECONDS', 120),
];
