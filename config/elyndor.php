<?php

return [
  /*
  | Token Bearer para POST /api/v1/internal/versao-desktop (script de release).
  */
  'deploy_token' => env('DEPLOY_TOKEN'),

  /*
  | Repositório GitHub do instalador (owner/repo). Usado para montar o link direto do .exe.
  */
  'desktop_github_repo' => env('DESKTOP_GITHUB_REPO', 'lucaswalmor/elyndor-releases'),

  /*
  | Prefixo do arquivo do instalador (ex.: Elyndor → Elyndor_0.1.2_x64-setup.exe).
  */
  'desktop_installer_basename' => env('DESKTOP_INSTALLER_BASENAME', 'Elyndor'),

  /*
  | Opcional: URL fixa do .exe (sobrescreve o link automático por versão).
  | Se vazio, usa releases/latest/download/Elyndor_{versao}_x64-setup.exe
  */
  'desktop_download_url' => env('DESKTOP_DOWNLOAD_URL'),

  /*
  | Validação de versão do cliente desktop (matchmaking e checagens server-side).
  | Desligada automaticamente em APP_ENV=local.
  */
  'desktop_version_check_enabled' => env('DESKTOP_VERSION_CHECK_ENABLED', true),
];
