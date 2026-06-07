<?php

return [
    /*
    | Token Bearer para POST /api/v1/internal/versao-desktop (script de release).
    */
    'deploy_token' => env('DEPLOY_TOKEN', ''),

    'desktop_github_repo' => env('ELYNDOR_DESKTOP_GITHUB_REPO', 'lucaswalmor/elyndor-releases'),

    'desktop_download_url' => env('ELYNDOR_DESKTOP_DOWNLOAD_URL', ''),

    'desktop_download_url_default' => env(
        'ELYNDOR_DESKTOP_DOWNLOAD_URL_DEFAULT',
        'https://drive.google.com/uc?export=download&id=1FwSR8YcKzbVIsOOm7Qn-lFnIF2JDroMr',
    ),

    'desktop_installer_basename' => env('ELYNDOR_DESKTOP_INSTALLER_BASENAME', 'Elyndor'),

    'desktop_version_check_enabled' => env('DESKTOP_VERSION_CHECK_ENABLED', true),

    /*
    | Tokens de convite para ativar conta de criador/streamer (uso único).
    | Ex.: STREAMER_INVITES=TOKEN_A,TOKEN_B
    */
    'streamer_invites' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('STREAMER_INVITES', ''))
    ))),
];
