<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Economy\PlayerLootGrantService;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

/**
 * Prémios manuais (streamers, beta, suporte) — mesma gravação que abrir um baú.
 *
 * Pacotes prontos: use self::TODOS_VERSOS, self::TODOS_TABULEIROS, etc.
 * Ao adicionar cosmético novo, atualiza aqui e em config/game/cosmetics.php.
 * Ao adicionar carta nova, atualiza self::TODAS_CARTAS (ou CartasSeeder).
 */
class ItemStreamerSeeder extends Seeder
{
    /** Todos os versos (card_back) — espelha config/game/cosmetics.php + exclusivos */
    private const TODOS_VERSOS = [
        'verso_padrao',
        'verso_comum',
        'verso_da_carta',
        'verso_astra_veil',
        'verso_infernais',
        'verso_natureza',
        'verso_ferrox',
        'verso_nocthar',
        'verso_sylvaris',
        'verso_vulkaris',
        'verso_premium_cristal',
        'verso_premium_jade',
        'verso_premium_obsidiana',
        'verso_premium_ouro',
        'verso_beta_tester',
        'verso_brasil_copa_2026',
        'versor_boomerangbr',
    ];

    /** Todos os tabuleiros (match_board) */
    private const TODOS_TABULEIROS = [
        'tabuleiro_padrao_v2',
        'tabuleiro_astra_veil',
        'tabuleiro_ferrox',
        'tabuleiro_nocthar',
        'tabuleiro_sylvaris',
        'tabuleiro_vulkaris',
        'tabuleiro_premium_cristal',
        'tabuleiro_premium_jade',
        'tabuleiro_premium_obsidiana',
        'tabuleiro_premium_ouro',
        'tabuleiro_brasil_copa_2026',
    ];

    /** Todos os fundos de perfil (profile_bg) */
    private const TODOS_FUNDOS_PERFIL = [
        'ui_bg_profile_standard',
        'ui_bg_profile_infernal',
        'ui_bg_profile_celestial',
        'ui_bg_profile_crystal',
        'ui_bg_profile_gold',
        'ui_bg_profile_heaven',
        'ui_bg_profile_jade',
        'ui_bg_profile_mechanics',
        'ui_bg_profile_nature',
        'ui_bg_profile_obsidian',
        'ui_bg_profile_undead',
    ];

    /** Todas as cartas colecionáveis (slug) — espelha CartasSeeder */
    private const TODAS_CARTAS = [
        'carniceiro-de-brasas',
        'cao-vulcanico',
        'bruxa-cinzenta',
        'tita-magmatico',
        'morcego-igneo',
        'rei-das-correntes',
        'guardiao-do-musgo',
        'aranha-lunar',
        'espirito-da-raiz',
        'sapo-toxico',
        'cervo-fantasma',
        'hidra-do-pantano',
        'drone-sentinela',
        'executor-de-ferro',
        'engenheira-tesla',
        'aranha-de-sucata',
        'tremor-mk-ii',
        'nucleo-automato',
        'cavaleiro-sem-face',
        'costureira-macabra',
        'corvo-funerario',
        'monge-apodrecido',
        'gigante-ossuario',
        'crianca-do-veu',
        'oraculo-solar',
        'aberracao-do-vazio',
        'serafim-partido',
        'eclipse-vivo',
        'navegante-astral',
        'devorador-de-estrelas',
    ];

    /**
     * @var list<array{
     *   user_id: int,
     *   rotulo: string,
     *   versos?: list<string>,
     *   tabuleiros?: list<string>,
     *   fundos_perfil?: list<string>,
     *   cartas?: list<string>,
     *   verso?: string|null,
     *   tabuleiro?: string|null,
     *   fundo_perfil?: string|null,
     * }>
     */
    private const CONCESSOES = [
        [
            'user_id' => 16,
            'rotulo' => 'BoomerangBR',
            'versos' => self::TODOS_VERSOS,
            'tabuleiros' => self::TODOS_TABULEIROS,
            'fundos_perfil' => self::TODOS_FUNDOS_PERFIL,
            'cartas' => self::TODAS_CARTAS,
        ],
        // Liberar tudo — descomenta e ajusta user_id:
        // [
        //     'user_id' => 99,
        //     'rotulo' => 'Conta teste — tudo',
        //     'versos' => self::TODOS_VERSOS,
        //     'tabuleiros' => self::TODOS_TABULEIROS,
        //     'fundos_perfil' => self::TODOS_FUNDOS_PERFIL,
        //     'cartas' => self::TODAS_CARTAS,
        // ],
        // Misturar pacote completo + itens à mão:
        // 'versos' => array_merge(self::TODOS_VERSOS, ['verso_extra']),
    ];

    public function run(): void
    {
        $concessoes = array_values(array_filter(
            self::CONCESSOES,
            fn (array $linha) => (int) ($linha['user_id'] ?? 0) > 0,
        ));

        if ($concessoes === []) {
            $this->command?->warn('ItemStreamerSeeder: define user_id > 0 em pelo menos uma entrada de CONCESSOES.');

            return;
        }

        $servico = app(PlayerLootGrantService::class);

        $contagemNovos = 0;
        $contagemRepetidas = 0;
        $contagemErros = 0;

        foreach ($concessoes as $concessao) {
            $userId = (int) $concessao['user_id'];
            $rotulo = (string) ($concessao['rotulo'] ?? "user#{$userId}");

            $usuario = User::query()->find($userId);
            if (! $usuario) {
                $this->command?->warn("[{$rotulo}] Utilizador #{$userId} não existe — ignorado.");
                $contagemErros++;

                continue;
            }

            $itens = $this->itensDaConcessao($concessao);
            if ($itens === []) {
                $this->command?->warn("[{$rotulo}] Nenhum item definido (arrays vazios).");

                continue;
            }

            $this->command?->info("— {$rotulo} (user #{$userId}) — ".count($itens).' item(ns)');

            foreach ($itens as $item) {
                try {
                    $resultado = match ($item['tipo']) {
                        'carta' => $servico->concederCartaPorSlug($usuario, $item['chave']),
                        'cosmetico' => $servico->concederCosmetico($usuario, $item['categoria'], $item['chave']),
                        default => throw new InvalidArgumentException('Tipo de item inválido.'),
                    };
                } catch (InvalidArgumentException $exception) {
                    $this->command?->warn('  ⊗ '.$this->rotuloItem($item).': '.$exception->getMessage());
                    $contagemErros++;

                    continue;
                }

                $this->command?->line('  '.$this->formatarResultado($item, $resultado));

                if (str_contains($resultado['status'], 'duplicad')) {
                    $contagemRepetidas++;
                } else {
                    $contagemNovos++;
                }
            }
        }

        $this->command?->info(
            "Resumo: {$contagemNovos} concedido(s), {$contagemRepetidas} em repetidas (já tinha / cópia extra), {$contagemErros} erro(s).",
        );
    }

    /**
     * @param  array<string, mixed>  $concessao
     * @return list<array{tipo: string, chave: string, categoria?: string}>
     */
    private function itensDaConcessao(array $concessao): array
    {
        $itens = [];

        foreach ($this->chavesLista($concessao, 'versos', 'verso') as $chave) {
            $itens[] = ['tipo' => 'cosmetico', 'categoria' => 'card_back', 'chave' => $chave];
        }

        foreach ($this->chavesLista($concessao, 'tabuleiros', 'tabuleiro') as $chave) {
            $itens[] = ['tipo' => 'cosmetico', 'categoria' => 'match_board', 'chave' => $chave];
        }

        foreach ($this->chavesLista($concessao, 'fundos_perfil', 'fundo_perfil') as $chave) {
            $itens[] = ['tipo' => 'cosmetico', 'categoria' => 'profile_bg', 'chave' => $chave];
        }

        foreach ($this->chavesLista($concessao, 'cartas', 'carta') as $slug) {
            $itens[] = ['tipo' => 'carta', 'chave' => $slug];
        }

        return $itens;
    }

    /**
     * Lista do array plural + valor singular opcional (sem duplicar chaves).
     *
     * @return list<string>
     */
    private function chavesLista(array $concessao, string $campoPlural, string $campoSingular): array
    {
        $chaves = [];

        $lista = $concessao[$campoPlural] ?? null;
        if (is_array($lista)) {
            foreach ($lista as $valor) {
                $texto = trim((string) $valor);
                if ($texto !== '') {
                    $chaves[] = $texto;
                }
            }
        }

        if (array_key_exists($campoSingular, $concessao)) {
            $texto = trim((string) $concessao[$campoSingular]);
            if ($texto !== '') {
                $chaves[] = $texto;
            }
        }

        return array_values(array_unique($chaves));
    }

    /**
     * @param  array{tipo: string, chave: string, categoria?: string}  $item
     * @param  array<string, mixed>  $resultado
     */
    private function formatarResultado(array $item, array $resultado): string
    {
        $rotulo = $this->rotuloItem($item);

        return match ($resultado['status']) {
            'carta_concedida' => "✓ {$rotulo}: carta na coleção ({$resultado['card_nome']})",
            'carta_duplicada_repetidas' => "↻ {$rotulo}: cópia extra → repetidas ({$resultado['card_nome']})",
            'cosmetico_novo' => "✓ {$rotulo}: desbloqueado",
            'cosmetico_duplicado_repetidas' => "↻ {$rotulo}: já tinha → repetidas",
            default => "? {$rotulo}: {$resultado['status']}",
        };
    }

    /**
     * @param  array{tipo: string, chave: string, categoria?: string}  $item
     */
    private function rotuloItem(array $item): string
    {
        if ($item['tipo'] === 'carta') {
            return "card/{$item['chave']}";
        }

        return ($item['categoria'] ?? 'cosmetico').'/'.$item['chave'];
    }
}
