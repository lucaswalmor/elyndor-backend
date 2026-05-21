<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function mapaLinhagem(): array
    {
        return config('game.linhagens.mapa_legado', []);
    }

    public function up(): void
    {
        $mapa = $this->mapaLinhagem();

        Schema::table('cards', function (Blueprint $table) {
            $table->renameColumn('faccao', 'linhagem');
        });

        foreach ($mapa as $antigo => $novo) {
            DB::table('cards')->where('linhagem', $antigo)->update(['linhagem' => $novo]);
        }

        foreach (DB::table('cards')->whereNotNull('imagem_path')->get(['id', 'imagem_path']) as $carta) {
            $caminho = (string) $carta->imagem_path;
            foreach ($mapa as $antigo => $novo) {
                $prefixo = $antigo.'/';
                if (str_starts_with($caminho, $prefixo)) {
                    DB::table('cards')->where('id', $carta->id)->update([
                        'imagem_path' => $novo.substr($caminho, strlen($antigo)),
                    ]);
                    break;
                }
            }
        }

        $this->migrarEfeitosCardSkills($mapa);

        Schema::table('community_decks', function (Blueprint $table) {
            $table->renameColumn('faccao_principal', 'linhagem_principal');
        });

        foreach ($mapa as $antigo => $novo) {
            DB::table('community_decks')->where('linhagem_principal', $antigo)->update(['linhagem_principal' => $novo]);
        }

        $mapaBaus = [
            'bau_cartas_infernais' => 'bau_cartas_karuna',
            'bau_cartas_natureza' => 'bau_cartas_ybyra',
            'bau_cartas_mecanicos' => 'bau_cartas_ferroveu',
            'bau_cartas_mortos_vivos' => 'bau_cartas_anhanga',
            'bau_cartas_celestiais' => 'bau_cartas_orun',
        ];

        if (Schema::hasTable('chests')) {
            foreach ($mapaBaus as $antigo => $novo) {
                DB::table('chests')->where('slug', $antigo)->update(['slug' => $novo]);
            }
        }
    }

    public function down(): void
    {
        $mapa = array_flip($this->mapaLinhagem());
        // Remove duplicatas de flip (celestiais e void → orun)
        $mapa = [
            'karuna' => 'infernais',
            'ybyra' => 'natureza',
            'ferroveu' => 'mecanicos',
            'anhanga' => 'mortos_vivos',
            'orun' => 'void',
        ];

        $mapaBaus = [
            'bau_cartas_karuna' => 'bau_cartas_infernais',
            'bau_cartas_ybyra' => 'bau_cartas_natureza',
            'bau_cartas_ferroveu' => 'bau_cartas_mecanicos',
            'bau_cartas_anhanga' => 'bau_cartas_mortos_vivos',
            'bau_cartas_orun' => 'bau_cartas_celestiais',
        ];

        if (Schema::hasTable('chests')) {
            foreach ($mapaBaus as $novo => $antigo) {
                DB::table('chests')->where('slug', $novo)->update(['slug' => $antigo]);
            }
        }

        foreach ($mapa as $novo => $antigo) {
            DB::table('community_decks')->where('linhagem_principal', $novo)->update(['linhagem_principal' => $antigo]);
        }

        Schema::table('community_decks', function (Blueprint $table) {
            $table->renameColumn('linhagem_principal', 'faccao_principal');
        });

        foreach ($mapa as $novo => $antigo) {
            DB::table('cards')->where('linhagem', $novo)->update(['linhagem' => $antigo]);
        }

        foreach (DB::table('cards')->whereNotNull('imagem_path')->get(['id', 'imagem_path']) as $carta) {
            $caminho = (string) $carta->imagem_path;
            foreach ($mapa as $novo => $antigo) {
                $prefixo = $novo.'/';
                if (str_starts_with($caminho, $prefixo)) {
                    DB::table('cards')->where('id', $carta->id)->update([
                        'imagem_path' => $antigo.substr($caminho, strlen($novo)),
                    ]);
                    break;
                }
            }
        }

        $this->migrarEfeitosCardSkills(array_flip([
            'infernais' => 'karuna',
            'natureza' => 'ybyra',
            'mecanicos' => 'ferroveu',
            'mortos_vivos' => 'anhanga',
            'void' => 'orun',
        ]), true);

        Schema::table('cards', function (Blueprint $table) {
            $table->renameColumn('linhagem', 'faccao');
        });
    }

    private function migrarEfeitosCardSkills(array $mapa, bool $reverter = false): void
    {
        $skills = DB::table('card_skills')->get();

        foreach ($skills as $skill) {
            $efeito = json_decode($skill->efeito, true);
            if (! is_array($efeito)) {
                continue;
            }

            $alterado = false;

            if ($reverter) {
                if (isset($efeito['filtro_linhagem'])) {
                    $efeito['filtro_faccao'] = $mapa[$efeito['filtro_linhagem']] ?? $efeito['filtro_linhagem'];
                    unset($efeito['filtro_linhagem']);
                    $alterado = true;
                }
            } elseif (isset($efeito['filtro_faccao'])) {
                $legado = $efeito['filtro_faccao'];
                $efeito['filtro_linhagem'] = $mapa[$legado] ?? $legado;
                unset($efeito['filtro_faccao']);
                $alterado = true;
            }

            if ($alterado) {
                DB::table('card_skills')->where('id', $skill->id)->update([
                    'efeito' => json_encode($efeito, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
    }
};
