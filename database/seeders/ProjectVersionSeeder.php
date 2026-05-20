<?php

namespace Database\Seeders;

use App\Models\ProjectVersion;
use Illuminate\Database\Seeder;

class ProjectVersionSeeder extends Seeder
{
    public function run(): void
    {
        ProjectVersion::query()->updateOrCreate(
            ['client_type' => ProjectVersion::CLIENT_DESKTOP],
            [
                'versao' => '0.1.0',
                'notas' => 'Versão inicial do cliente desktop (Beta).',
            ],
        );

        ProjectVersion::query()->updateOrCreate(
            ['client_type' => ProjectVersion::CLIENT_GAME],
            [
                'versao' => '0.1.0',
                'notas' => 'Versão global do jogo (cartas/meta) — bump ao alterar balanceamento.',
            ],
        );
    }
}
