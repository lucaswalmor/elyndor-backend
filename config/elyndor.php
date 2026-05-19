<?php

return [
  /*
  | Token Bearer para POST /api/v1/internal/versao-desktop (script de release).
  */
  'deploy_token' => env('DEPLOY_TOKEN'),

  /*
  | Link de download do instalador desktop (ex.: GitHub releases/latest).
  */
  'desktop_download_url' => env(
    'DESKTOP_DOWNLOAD_URL',
    'https://github.com/lucaswalmor/elyndor-frontend/releases/latest',
  ),

  /*
  | Validação de versão do cliente desktop (matchmaking e checagens server-side).
  | Desligada automaticamente em APP_ENV=local.
  */
  'desktop_version_check_enabled' => env('DESKTOP_VERSION_CHECK_ENABLED', true),
];
