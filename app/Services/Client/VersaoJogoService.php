<?php

namespace App\Services\Client;

use App\Models\ProjectVersion;

class VersaoJogoService
{
    public function versaoAtual(): string
    {
        $registro = ProjectVersion::query()
            ->where('client_type', ProjectVersion::CLIENT_GAME)
            ->first();

        return $registro?->versao ?? '0.1.0';
    }
}
