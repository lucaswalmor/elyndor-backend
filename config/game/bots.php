<?php

/**
 * Oponentes substitutos (bots) — lançamento.
 * Ranqueada: 1 perfil por divisão (ferro…mestre). Pontos vs bot = tabela × ranked_points_multiplier.
 * Nunca expor is_bot ao cliente. Ver roadmap/matchmaking.md
 */
return [
  'enabled' => env('BOTS_ENABLED', true),

  'queue' => [
    'casual_fallback_after_seconds' => (int) env('BOTS_CASUAL_FALLBACK_SECONDS', 20),
    'ranked_fallback_after_seconds' => (int) env('BOTS_RANKED_FALLBACK_SECONDS', 30),
  ],

  // Ranqueada vs substituto: partida normal, pontos = tabela humana × multiplier (0.5 → ±11)
  'ranked_points_multiplier' => 0.5,

  'disguise' => [
    'think_delay_min_ms' => 2000,
    'think_delay_max_ms' => 8000,
  ],

  'difficulties' => [
    // Fila normal: acima do antigo “ouro” (0.65) — desafio consistente
    'casual' => [
      'slug' => 'casual',
      'aggression' => 0.78,
      'mistake_chance' => 0.04,
    ],
    // Escala deslocada: perfil que era ouro → bronze; demais divisões +1 tier
    'ranked' => [
      'ferro' => ['aggression' => 0.45, 'mistake_chance' => 0.10],
      'bronze' => ['aggression' => 0.65, 'mistake_chance' => 0.06],
      'prata' => ['aggression' => 0.72, 'mistake_chance' => 0.05],
      'ouro' => ['aggression' => 0.78, 'mistake_chance' => 0.04],
      'platina' => ['aggression' => 0.85, 'mistake_chance' => 0.03],
      'diamante' => ['aggression' => 0.88, 'mistake_chance' => 0.025],
      'mestre' => ['aggression' => 0.92, 'mistake_chance' => 0.02],
    ],
  ],
];
