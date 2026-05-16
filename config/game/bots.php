<?php

/**
 * Oponentes substitutos (bots) — lançamento.
 * Ranqueada: 1 perfil por divisão (ferro…mestre). Pontos vs bot = tabela × ranked_points_multiplier.
 * Nunca expor is_bot ao cliente. Ver roadmap/matchmaking.md
 */
return [
  'enabled' => env('BOTS_ENABLED', false),

  'queue' => [
    'casual_fallback_after_seconds' => 20,
    'ranked_fallback_after_seconds' => 30,
  ],

  // Ranqueada vs substituto: partida normal, pontos = tabela humana × multiplier (0.5 → ±11)
  'ranked_points_multiplier' => 0.5,

  'disguise' => [
    'think_delay_min_ms' => 2000,
    'think_delay_max_ms' => 8000,
  ],

  'difficulties' => [
    'casual' => [
      'slug' => 'casual',
      'aggression' => 0.4,
      'mistake_chance' => 0.15,
    ],
    'ranked' => [
      'ferro' => ['aggression' => 0.35, 'mistake_chance' => 0.12],
      'bronze' => ['aggression' => 0.45, 'mistake_chance' => 0.10],
      'prata' => ['aggression' => 0.55, 'mistake_chance' => 0.08],
      'ouro' => ['aggression' => 0.65, 'mistake_chance' => 0.06],
      'platina' => ['aggression' => 0.72, 'mistake_chance' => 0.05],
      'diamante' => ['aggression' => 0.78, 'mistake_chance' => 0.04],
      'mestre' => ['aggression' => 0.85, 'mistake_chance' => 0.03],
    ],
  ],
];
